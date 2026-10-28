<?php

namespace Modules\Purchasing\Services;

use App\Enums\ActivityLogAction;
use App\Services\BaseService;
use App\Support\Documents\DocumentNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\StockService;
use Modules\Purchasing\Enums\PurchaseStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseOrderLine;
use Modules\Purchasing\Models\PurchaseReceipt;
use Modules\Purchasing\Models\PurchaseReceiptLine;
use Modules\Purchasing\Models\Supplier;
use Modules\Purchasing\Models\SupplierPayment;
use RuntimeException;

class PurchasingService extends BaseService
{
    public function __construct(
        protected DocumentNumber $numbers,
        protected StockService $stock,
    ) {
    }

    /**
     * @param  list<array{variant: Variant, description: ?string, quantity: float, unit_price: float, line_total: float}>  $lines  unit_price is the unit cost
     */
    public function createOrder(array $data, array $lines): array
    {
        $order = DB::transaction(function () use ($data, $lines) {
            $order = new PurchaseOrder($data);
            $order->number = $this->numbers->next('purchase_order', (string) config('purchasing.prefix', 'PO'));
            $order->status = PurchaseStatus::Draft;
            $order->save();
            $this->writeLines($order, $lines);

            return $order;
        });

        return $this->success($order, __('purchasing::purchasing.order_created', ['number' => $order->number]));
    }

    public function updateOrder(PurchaseOrder $order, array $data, array $lines): array
    {
        if ($order->status !== PurchaseStatus::Draft) {
            return $this->failure(__('purchasing::purchasing.order_locked'));
        }

        DB::transaction(function () use ($order, $data, $lines) {
            $order->fill($data)->save();
            $order->lines()->delete();
            $this->writeLines($order, $lines);
        });

        return $this->success($order, __('purchasing::purchasing.order_updated'));
    }

    /**
     * Sent to the supplier: lines are locked from now on.
     */
    public function markOrdered(PurchaseOrder $order): array
    {
        if ($order->status !== PurchaseStatus::Draft) {
            return $this->failure(__('purchasing::purchasing.order_locked'));
        }

        $this->transition($order, PurchaseStatus::Ordered);

        return $this->success($order, __('purchasing::purchasing.order_sent'));
    }

    public function cancelOrder(PurchaseOrder $order, string $reason): array
    {
        if (! in_array($order->status, [PurchaseStatus::Draft, PurchaseStatus::Ordered], true)) {
            return $this->failure(__('purchasing::purchasing.cannot_cancel'));
        }

        $this->transition($order, PurchaseStatus::Cancelled, ['cancel_reason' => mb_substr($reason, 0, 500)]);

        return $this->success($order, __('purchasing::purchasing.order_cancelled'));
    }

    /**
     * Goods arrived (all or part): [order line id => quantity]. Stock goes in at the line's cost.
     */
    public function receive(PurchaseOrder $order, array $quantities, array $data): array
    {
        if (! $order->status->canReceive()) {
            return $this->failure(__('purchasing::purchasing.cannot_receive'));
        }

        try {
            $receipt = DB::transaction(function () use ($order, $quantities, $data) {
                $order->load(['lines.variant', 'store']);
                $receipt = new PurchaseReceipt($data);
                $receipt->business_id = $order->business_id;
                $receipt->purchase_order_id = $order->id;
                $receipt->supplier_id = $order->supplier_id;
                $receipt->number = $this->numbers->next('goods_received', (string) config('purchasing.receipt_prefix', 'GRN'));
                $receipt->save();

                $moves = [];
                $value = 0;

                foreach ($order->lines as $line) {
                    $quantity = round((float) ($quantities[$line->id] ?? 0), 3);

                    if ($quantity <= 0) {
                        continue;
                    }

                    if ($quantity > $line->remaining() + 0.0005) {
                        throw new RuntimeException(__('purchasing::purchasing.receive_too_much', [
                            'remaining' => InsufficientStock::format($line->remaining()), 'item' => $line->variant?->displayName(),
                        ]));
                    }

                    $line->forceFill(['received_quantity' => round($line->received_quantity + $quantity, 3)])->save();
                    $row = new PurchaseReceiptLine(['order_line_id' => $line->id, 'variant_id' => $line->variant_id, 'quantity' => $quantity, 'unit_cost' => $line->unit_cost]);
                    $row->business_id = $order->business_id;
                    $row->purchase_receipt_id = $receipt->id;
                    $row->save();

                    $moves[] = ['variant' => $line->variant, 'store' => $order->store, 'quantity' => $quantity, 'unit_cost' => $line->unit_cost];
                    $value += $quantity * (float) $line->unit_cost;
                }

                if ($moves === []) {
                    throw new RuntimeException(__('purchasing::purchasing.receive_nothing'));
                }

                $receipt->forceFill(['value' => round($value, 2)])->save();
                $this->stock->record(MovementType::Purchase, $moves, __('purchasing::purchasing.received_from', ['supplier' => $order->supplier?->name]), $receipt, $receipt->number.' / '.$order->number);

                $complete = $order->lines->every(fn (PurchaseOrderLine $line) => $line->remaining() <= 0.0005);
                $this->transition($order, $complete ? PurchaseStatus::Received : PurchaseStatus::PartlyReceived);

                return $receipt;
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($receipt, __('purchasing::purchasing.received', ['number' => $receipt->number]));
    }

    /**
     * What the business owes the supplier: goods received minus payments.
     */
    public function balance(Supplier $supplier): float
    {
        $received = (float) PurchaseReceipt::query()->where('supplier_id', $supplier->id)->sum('value');
        $paid = (float) SupplierPayment::query()->where('supplier_id', $supplier->id)->whereNull('reversed_at')->sum('amount');

        return round($received - $paid, 2);
    }

    public function pay(Supplier $supplier, array $data): array
    {
        try {
            $payment = DB::transaction(function () use ($supplier, $data) {
                Supplier::query()->whereKey($supplier->id)->lockForUpdate()->first();
                $amount = round((float) $data['amount'], 2);
                $balance = $this->balance($supplier);

                if ($amount > $balance + 0.001) {
                    throw new RuntimeException(__('purchasing::purchasing.overpayment', ['balance' => money($balance)]));
                }

                $payment = new SupplierPayment($data + ['amount' => $amount]);
                $payment->business_id = $supplier->business_id;
                $payment->supplier_id = $supplier->id;
                $payment->number = $this->numbers->next('supplier_payment', (string) config('purchasing.payment_prefix', 'SP'));
                $payment->save();

                return $payment;
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($payment, __('purchasing::purchasing.payment_saved', ['number' => $payment->number]));
    }

    public function reversePayment(SupplierPayment $payment, string $reason): array
    {
        if ($payment->isReversed()) {
            return $this->failure(__('purchasing::purchasing.already_reversed'));
        }

        $payment->forceFill(['reversed_at' => now(), 'reverse_reason' => mb_substr($reason, 0, 500)])->save();
        audit(ActivityLogAction::Updated, __('purchasing::purchasing.audit_reversed', ['number' => $payment->number, 'reason' => $reason]), $payment);

        return $this->success($payment, __('purchasing::purchasing.payment_reversed'));
    }

    /**
     * Variants at or below their reorder level, counting what is already on order.
     *
     * @return Collection<int, array{variant: Variant, on_hand: float, on_order: float, reorder: float, suggest: float}>
     */
    public function reorderList(): Collection
    {
        $onOrder = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseStatus::Draft->value, PurchaseStatus::Ordered->value, PurchaseStatus::PartlyReceived->value]))
            ->selectRaw('variant_id, SUM(quantity - received_quantity) as qty')
            ->groupBy('variant_id')->pluck('qty', 'variant_id');
        $onHand = StockLevel::query()->selectRaw('variant_id, SUM(quantity) as qty')->groupBy('variant_id')->pluck('qty', 'variant_id');

        return Variant::query()->with('item')
            ->where('is_active', true)
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->get()
            ->map(function (Variant $variant) use ($onOrder, $onHand) {
                $reorder = $variant->reorderLevel();
                $have = (float) ($onHand[$variant->id] ?? 0);
                $coming = (float) ($onOrder[$variant->id] ?? 0);

                return $reorder === null || $have + $coming > $reorder ? null : [
                    'variant' => $variant,
                    'on_hand' => $have,
                    'on_order' => $coming,
                    'reorder' => $reorder,
                    // Enough to get back to twice the reorder level.
                    'suggest' => max(1, round($reorder * 2 - $have - $coming, 3)),
                ];
            })
            ->filter()
            ->sortBy(fn ($row) => mb_strtolower($row['variant']->displayName()))
            ->values();
    }

    private function writeLines(PurchaseOrder $order, array $lines): void
    {
        foreach (array_values($lines) as $position => $line) {
            $row = new PurchaseOrderLine([
                'variant_id' => $line['variant']->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
            $row->business_id = $order->business_id;
            $row->purchase_order_id = $order->id;
            $row->save();
        }

        $order->forceFill(['total' => (float) $order->lines()->sum('line_total')])->save();
    }

    private function transition(PurchaseOrder $order, PurchaseStatus $to, array $extra = []): void
    {
        $from = $order->status;
        $order->forceFill(['status' => $to] + $extra)->save();

        audit(ActivityLogAction::Updated, __('purchasing::purchasing.audit_status', [
            'number' => $order->number, 'from' => $from->label(), 'to' => $to->label(),
        ]), $order);
    }
}
