<?php

namespace Modules\Production\Services;

use App\Enums\ActivityLogAction;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use App\Support\Documents\DocumentNumber;
use App\Support\Settings\BusinessSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\StockService;
use Modules\Production\Enums\WorkOrderStatus;
use Modules\Production\Models\ProductionEntry;
use Modules\Production\Models\WorkOrder;

class WorkOrderService extends BaseService
{
    public const FILTERS = ['q', 'status', 'line'];

    public function __construct(
        protected DocumentNumber $numbers,
        protected StockService $stock,
        protected BusinessSettings $settings,
        protected ModuleManager $modules,
    ) {
    }

    /**
     * This business's stages, first to last. The last one is "finished".
     *
     * @return list<string>
     */
    public function stages(): array
    {
        $stages = array_values(array_filter((array) $this->settings->get('production.stages', config('production.stages'))));

        return $stages ?: ['Finished'];
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)->with('variant.item')->latest('id')->paginate($perPage)->withQueryString();
    }

    public function filtered(array $filters): Builder
    {
        return WorkOrder::query()
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $query, string $q) {
                $like = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(number) LIKE ?', [$like])
                    ->orWhereHas('variant.item', fn ($i) => $i->whereRaw('LOWER(name) LIKE ?', [$like])));
            })
            ->when(WorkOrderStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereIn('status', [WorkOrderStatus::Planned->value, WorkOrderStatus::InProgress->value]))
            ->when($filters['line'] ?? null, fn ($q, $line) => $q->where('line', $line));
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return WorkOrder::query()->whereNotNull('line')->distinct()->orderBy('line')->pluck('line')->all();
    }

    public function create(array $data): array
    {
        $order = DB::transaction(function () use ($data) {
            $stages = $this->stages();
            $order = new WorkOrder($data);
            $order->number = $this->numbers->next('work_order', (string) config('production.prefix', 'WO'));
            $order->status = WorkOrderStatus::Planned;
            $order->stages = $stages;
            $order->counts = array_fill(0, count($stages), 0);
            $order->save();

            return $order;
        });

        $this->syncSalesOrder($order);

        return $this->success($order, __('production::production.created', ['number' => $order->number]));
    }

    public function update(WorkOrder $order, array $data): array
    {
        if (! $order->status->isOpen()) {
            return $this->failure(__('production::production.closed'));
        }

        // Product and store are fixed once pieces exist at any stage.
        if (array_sum($order->counts) > 0) {
            unset($data['variant_id'], $data['store_id']);
        }

        if (isset($data['quantity']) && (float) $data['quantity'] < $order->count(0)) {
            return $this->failure(__('production::production.quantity_below_cut', ['count' => InsufficientStock::format($order->count(0))]));
        }

        $order->fill($data)->save();

        return $this->success($order, __('production::production.updated'));
    }

    /**
     * Add (or, with a note, correct) the count at one stage.
     */
    public function record(WorkOrder $order, int $stage, float $quantity, ?string $workDate = null, ?string $note = null): array
    {
        $quantity = round($quantity, 3);

        if (abs($quantity) < 0.0005) {
            return $this->failure(__('production::production.quantity_zero'));
        }

        if ($quantity < 0 && blank($note)) {
            return $this->failure(__('production::production.correction_needs_note'));
        }

        try {
            $order = DB::transaction(function () use ($order, $stage, $quantity, $workDate, $note) {
                /** @var WorkOrder $locked */
                $locked = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (! $locked->status->isOpen()) {
                    throw new ProductionRuleBroken(__('production::production.closed'));
                }

                if ($stage < 0 || $stage > $locked->lastStage()) {
                    throw new ProductionRuleBroken(__('production::production.unknown_stage'));
                }

                $counts = array_map('floatval', $locked->counts);
                $new = round($counts[$stage] + $quantity, 3);
                $name = $locked->stages[$stage];

                if ($new < 0) {
                    throw new ProductionRuleBroken(__('production::production.below_zero', ['stage' => $name]));
                }

                // Never more at a stage than came out of the one before it (or than planned).
                // At the last stage, rejected pieces also came out of the previous one.
                $ceiling = $stage === 0 ? $locked->quantity : $counts[$stage - 1];
                if ($stage === $locked->lastStage()) {
                    $ceiling -= $locked->rejected;
                }
                if ($new > $ceiling + 0.0005) {
                    throw new ProductionRuleBroken($stage === 0
                        ? __('production::production.above_planned', ['stage' => $name, 'max' => InsufficientStock::format($ceiling)])
                        : __('production::production.above_previous', ['stage' => $name, 'previous' => $locked->stages[$stage - 1], 'max' => InsufficientStock::format($ceiling)]));
                }

                // Never less than what already went on to the next stage (plus rejects before the last).
                $floor = $stage < $locked->lastStage() ? $counts[$stage + 1] + ($stage + 1 === $locked->lastStage() ? $locked->rejected : 0) : 0;
                if ($stage < $locked->lastStage() && $new < $floor - 0.0005) {
                    throw new ProductionRuleBroken(__('production::production.below_next', [
                        'stage' => $name, 'next' => $locked->stages[$stage + 1], 'min' => InsufficientStock::format($floor),
                    ]));
                }

                // Finished pieces go into (or, when corrected down, come out of) stock.
                if ($stage === $locked->lastStage()) {
                    $locked->loadMissing(['variant', 'store']);
                    $this->stock->record(MovementType::ProductionIn, [[
                        'variant' => $locked->variant, 'store' => $locked->store, 'quantity' => $quantity,
                    ]], $note ?? __('production::production.finished_reason'), $locked, $locked->number);
                }

                $counts[$stage] = $new;
                $locked->counts = $counts;

                if ($locked->status === WorkOrderStatus::Planned) {
                    $locked->status = WorkOrderStatus::InProgress;
                }

                if ($stage === $locked->lastStage() && $new + $locked->rejected >= $locked->quantity - 0.0005) {
                    $locked->status = WorkOrderStatus::Completed;
                    $locked->completed_at = now();
                }

                $locked->save();

                $entry = new ProductionEntry;
                $entry->forceFill([
                    'business_id' => $locked->business_id,
                    'work_order_id' => $locked->id,
                    'stage' => $stage,
                    'stage_name' => $name,
                    'quantity' => $quantity,
                    'count_after' => $new,
                    'work_date' => $workDate ?: now()->toDateString(),
                    'line' => $locked->line,
                    'note' => $note ? mb_substr($note, 0, 500) : null,
                    'user_id' => Auth::id(),
                ])->save();

                audit($quantity > 0 ? 'production_count' : 'production_correction', __('production::production.audit_count', [
                    'number' => $locked->number, 'stage' => $name,
                    'quantity' => ($quantity > 0 ? '+' : '').InsufficientStock::format($quantity),
                    'total' => InsufficientStock::format($new),
                ]), $locked, ['stage' => $name, 'quantity' => $quantity, 'count_after' => $new, 'note' => $note]);

                return $locked;
            });
        } catch (ProductionRuleBroken $e) {
            return $this->failure($e->getMessage());
        } catch (InsufficientStock $e) {
            return $this->failure(__('production::production.finished_already_used', ['detail' => $e->getMessage()]));
        }

        $this->syncSalesOrder($order);

        return $this->success($order, __('production::production.recorded'));
    }

    /**
     * Quality control: pieces that failed at the last stage. They never go into stock.
     * A minus number (with a note) corrects a mistake.
     */
    public function reject(WorkOrder $order, float $quantity, string $reason): array
    {
        $quantity = round($quantity, 3);

        if (abs($quantity) < 0.0005 || blank($reason)) {
            return $this->failure(__('production::production.reject_needs_reason'));
        }

        try {
            $order = DB::transaction(function () use ($order, $quantity, $reason) {
                $locked = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (! $locked->status->isOpen()) {
                    throw new ProductionRuleBroken(__('production::production.closed'));
                }

                $last = $locked->lastStage();
                $rejected = round($locked->rejected + $quantity, 3);
                $available = $last === 0 ? $locked->quantity : $locked->count($last - 1);

                if ($rejected < 0) {
                    throw new ProductionRuleBroken(__('production::production.reject_below_zero'));
                }

                if ($locked->finished() + $rejected > $available + 0.0005) {
                    throw new ProductionRuleBroken(__('production::production.reject_too_many', [
                        'max' => InsufficientStock::format(max(0, $available - $locked->finished() - $locked->rejected)),
                        'stage' => $last === 0 ? __('production::production.quantity') : $locked->stages[$last - 1],
                    ]));
                }

                $locked->rejected = $rejected;
                if ($locked->finished() + $rejected >= $locked->quantity - 0.0005) {
                    $locked->status = WorkOrderStatus::Completed;
                    $locked->completed_at = now();
                }
                $locked->save();

                $entry = new ProductionEntry;
                $entry->forceFill([
                    'business_id' => $locked->business_id,
                    'work_order_id' => $locked->id,
                    'stage' => $last,
                    'stage_name' => $locked->stages[$last],
                    'kind' => 'reject',
                    'quantity' => $quantity,
                    'count_after' => $rejected,
                    'work_date' => now()->toDateString(),
                    'line' => $locked->line,
                    'note' => mb_substr($reason, 0, 500),
                    'user_id' => Auth::id(),
                ])->save();

                audit('production_reject', __('production::production.audit_reject', [
                    'number' => $locked->number, 'quantity' => InsufficientStock::format($quantity), 'reason' => $reason,
                ]), $locked, ['quantity' => $quantity, 'rejected' => $rejected]);

                return $locked;
            });
        } catch (ProductionRuleBroken $e) {
            return $this->failure($e->getMessage());
        }

        $this->syncSalesOrder($order);

        return $this->success($order, __('production::production.reject_recorded'));
    }

    /**
     * Close a work order early (e.g. fewer pieces made). Finished pieces stay in stock.
     */
    public function complete(WorkOrder $order): array
    {
        if (! $order->status->isOpen()) {
            return $this->failure(__('production::production.closed'));
        }

        $this->transition($order, WorkOrderStatus::Completed, ['completed_at' => now()]);
        $this->syncSalesOrder($order);

        return $this->success($order, __('production::production.completed'));
    }

    public function cancel(WorkOrder $order, string $reason): array
    {
        if (! $order->status->isOpen()) {
            return $this->failure(__('production::production.closed'));
        }

        $this->transition($order, WorkOrderStatus::Cancelled, ['notes' => trim(($order->notes ? $order->notes."\n" : '').__('production::production.cancelled_because', ['reason' => $reason]))]);

        return $this->success($order, __('production::production.cancelled'));
    }

    /**
     * Take materials out of a store for this work order.
     *
     * @param  list<array{variant: \Modules\Inventory\Models\Variant, quantity: float}>  $lines
     */
    public function issueMaterials(WorkOrder $order, Store $store, array $lines, ?string $note = null): array
    {
        if ($order->status === WorkOrderStatus::Cancelled) {
            return $this->failure(__('production::production.closed'));
        }

        try {
            $this->stock->record(MovementType::ProductionOut, array_map(fn ($line) => [
                'variant' => $line['variant'], 'store' => $store, 'quantity' => -abs($line['quantity']),
            ], $lines), $note ?: __('production::production.materials_reason'), $order, $order->number);
        } catch (InsufficientStock $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($order, __('production::production.materials_issued'));
    }

    /**
     * Finished pieces per line today, against the line's daily target.
     *
     * @return list<array{line: string, done: float, target: ?float}>
     */
    public function today(): array
    {
        // Entries at each work order's last stage, grouped by line.
        $rows = ProductionEntry::query()
            ->join('production_work_orders as wo', 'wo.id', '=', 'production_entries.work_order_id')
            ->whereDate('production_entries.work_date', now()->toDateString())
            ->where('production_entries.kind', 'count')
            ->get(['production_entries.*', 'wo.stages as wo_stages', 'wo.daily_target as wo_target']);

        return $rows
            ->filter(fn ($entry) => $entry->stage === count(json_decode($entry->wo_stages, true)) - 1)
            ->groupBy(fn ($entry) => $entry->line ?: '—')
            ->map(fn ($entries, $line) => [
                'line' => $line,
                'done' => (float) $entries->sum('quantity'),
                'target' => (float) WorkOrder::query()->where('line', $line === '—' ? null : $line)
                    ->whereIn('status', [WorkOrderStatus::Planned->value, WorkOrderStatus::InProgress->value])
                    ->max('daily_target') ?: null,
            ])
            ->values()
            ->all();
    }

    private function transition(WorkOrder $order, WorkOrderStatus $to, array $extra = []): void
    {
        $from = $order->status;
        $order->forceFill(['status' => $to] + $extra)->save();

        audit(ActivityLogAction::Updated, __('production::production.audit_status', [
            'number' => $order->number, 'from' => $from->label(), 'to' => $to->label(),
        ]), $order);
    }

    /**
     * Keep a linked sales order in step: In production once work starts,
     * Ready when all its work orders are done.
     */
    private function syncSalesOrder(WorkOrder $order): void
    {
        if (! $order->sales_order_id || ! $this->modules->isActive('SalesOrders')) {
            return;
        }

        $salesOrder = \Modules\SalesOrders\Models\SalesOrder::query()->find($order->sales_order_id);
        $orders = app(\Modules\SalesOrders\Services\OrderService::class);
        $status = \Modules\SalesOrders\Enums\OrderStatus::class;

        if (! $salesOrder || ! $salesOrder->status->reservesStock()) {
            return;
        }

        $workOrders = WorkOrder::query()->where('sales_order_id', $salesOrder->id)->where('status', '!=', WorkOrderStatus::Cancelled->value)->get();
        $allDone = $workOrders->isNotEmpty() && $workOrders->every(fn (WorkOrder $w) => $w->status === WorkOrderStatus::Completed);
        $started = $workOrders->contains(fn (WorkOrder $w) => $w->status !== WorkOrderStatus::Planned);

        if ($allDone && $salesOrder->status !== $status::Ready) {
            $orders->progress($salesOrder, $status::Ready);
        } elseif (! $allDone && $started && $salesOrder->status === $status::Confirmed) {
            $orders->progress($salesOrder, $status::InProduction);
        }
    }
}
