<?php

namespace Modules\SalesOrders\Services;

use App\Enums\ActivityLogAction;
use App\Libraries\ListExport;
use App\Services\BaseService;
use App\Support\Documents\DocumentNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Services\StockService;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;
use Modules\SalesOrders\Models\SalesOrderLine;

class OrderService extends BaseService
{
    public const FILTERS = ['q', 'status', 'customer', 'from', 'to', 'overdue'];

    public function __construct(
        protected DocumentNumber $numbers,
        protected StockService $stock,
    ) {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)->with('customer')->latest('order_date')->latest('id')->paginate($perPage)->withQueryString();
    }

    public function exportList(array $filters): ListExport
    {
        $rows = $this->filtered($filters)->with('customer')->latest('order_date')->limit(5000)->get()->map(fn (SalesOrder $o) => [
            $o->number,
            $o->customer?->displayName() ?? '—',
            $o->order_date->format('Y-m-d'),
            $o->due_date?->format('Y-m-d') ?? '—',
            $o->status->label(),
            number_format((float) $o->total, 2),
        ])->all();

        return ListExport::make(__('salesorders::orders.orders'), 'sales-orders', [
            __('salesorders::orders.number'), __('salesorders::orders.customer'), __('salesorders::orders.order_date'),
            __('salesorders::orders.due_date'), __('body.status'), __('inventory::inventory.total'),
        ], $rows);
    }

    public function filtered(array $filters): Builder
    {
        return SalesOrder::query()
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $query, string $q) {
                $like = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(customer_reference) LIKE ?', [$like])
                    ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(company_name) LIKE ?', [$like])));
            })
            ->when(OrderStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($q, $status) => $q->where('status', $status->value))
            ->when($filters['customer'] ?? null, fn ($q, $uuid) => $q->whereHas('customer', fn ($c) => $c->where('uuid', $uuid)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('order_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('order_date', '<=', $to))
            ->when(! empty($filters['overdue']), fn ($q) => $q->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value]));
    }

    /**
     * @param  list<array{variant: \Modules\Inventory\Models\Variant, description: ?string, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    public function create(array $data, array $lines): array
    {
        $order = DB::transaction(function () use ($data, $lines) {
            $order = new SalesOrder($data);
            $order->number = $this->numbers->next('sales_order', (string) config('salesorders.prefix', 'SO'));
            $order->status = OrderStatus::Pending;
            $order->save();

            $this->writeLines($order, $lines);

            return $order;
        });

        return $this->success($order, __('salesorders::orders.created', ['number' => $order->number]));
    }

    /**
     * Header can change until delivery; lines only while pending.
     */
    public function update(SalesOrder $order, array $data, ?array $lines): array
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return $this->failure(__('salesorders::orders.locked'));
        }

        if ($lines !== null && ! $order->isEditable()) {
            return $this->failure(__('salesorders::orders.lines_locked'));
        }

        DB::transaction(function () use ($order, $data, $lines) {
            $order->fill($data)->save();

            if ($lines !== null) {
                $order->lines()->delete();
                $this->writeLines($order, $lines);
            } else {
                $this->recalculate($order);
            }
        });

        return $this->success($order, __('salesorders::orders.updated'));
    }

    public function confirm(SalesOrder $order): array
    {
        if ($order->status !== OrderStatus::Pending) {
            return $this->failure(__('salesorders::orders.not_pending'));
        }

        $this->transition($order, OrderStatus::Confirmed, ['confirmed_at' => now()]);

        return $this->success($order, __('salesorders::orders.confirmed'));
    }

    /**
     * Manual progress between Confirmed, In production and Ready.
     */
    public function progress(SalesOrder $order, OrderStatus $status): array
    {
        if (! in_array($order->status, OrderStatus::progress(), true) || ! in_array($status, OrderStatus::progress(), true)) {
            return $this->failure(__('salesorders::orders.cannot_change'));
        }

        $this->transition($order, $status);

        return $this->success($order, __('salesorders::orders.status_updated'));
    }

    /**
     * Hand the goods over: stock leaves the order's store (all or nothing).
     */
    public function deliver(SalesOrder $order): array
    {
        if (! $order->status->reservesStock()) {
            return $this->failure(__('salesorders::orders.cannot_deliver'));
        }

        try {
            DB::transaction(function () use ($order) {
                $order->loadMissing(['lines.variant', 'store']);

                $this->stock->record(
                    MovementType::Sale,
                    $order->lines->map(fn (SalesOrderLine $line) => [
                        'variant' => $line->variant,
                        'store' => $order->store,
                        'quantity' => -$line->quantity,
                    ])->all(),
                    __('salesorders::orders.delivery_reason', ['customer' => $order->customer?->displayName()]),
                    $order,
                    $order->number,
                );

                $this->transition($order, OrderStatus::Delivered, ['delivered_at' => now()]);
            });
        } catch (InsufficientStock $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($order, __('salesorders::orders.delivered'));
    }

    /**
     * Not after delivery (the goods are gone; use a return). Reserved stock is released.
     */
    public function cancel(SalesOrder $order, string $reason): array
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return $this->failure(__('salesorders::orders.cannot_cancel'));
        }

        $this->transition($order, OrderStatus::Cancelled, ['cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 500)]);

        return $this->success($order, __('salesorders::orders.cancelled'));
    }

    /**
     * Quantities on open (confirmed, not delivered) orders: [variant_id => quantity].
     *
     * @param  list<int>  $variantIds
     * @return array<int, float>
     */
    public function reserved(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return SalesOrderLine::query()
            ->whereIn('variant_id', $variantIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', array_map(
                fn (OrderStatus $s) => $s->value,
                array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->reservesStock()),
            )))
            ->selectRaw('variant_id, SUM(quantity) as total')
            ->groupBy('variant_id')
            ->pluck('total', 'variant_id')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    private function writeLines(SalesOrder $order, array $lines): void
    {
        foreach (array_values($lines) as $position => $line) {
            $row = new SalesOrderLine([
                'variant_id' => $line['variant']->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
            $row->business_id = $order->business_id;
            $row->order_id = $order->id;
            $row->save();
        }

        $this->recalculate($order);
    }

    private function recalculate(SalesOrder $order): void
    {
        $subtotal = (float) $order->lines()->sum('line_total');
        $discount = min((float) $order->discount, $subtotal);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => round($subtotal - $discount, 2),
        ])->save();
    }

    private function transition(SalesOrder $order, OrderStatus $to, array $extra = []): void
    {
        $from = $order->status;
        $order->forceFill(['status' => $to] + $extra)->save();

        audit(ActivityLogAction::Updated, __('salesorders::orders.audit_status', [
            'number' => $order->number, 'from' => $from->label(), 'to' => $to->label(),
        ]), $order, ['from' => $from->value, 'to' => $to->value] + array_map(fn ($v) => (string) $v, $extra));
    }
}
