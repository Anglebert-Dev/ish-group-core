<?php

namespace Modules\Production\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Production\Enums\WorkOrderStatus;
use Modules\Production\Models\WorkOrder;

/**
 * The job sheet that goes to the floor: what to make, how many, by when,
 * and each stage with room to tick off.
 */
class WorkOrderPrintable extends Printable
{
    public function label(): string
    {
        return __('production::production.print_work_order');
    }

    public function permission(): string
    {
        return 'read production.work_order';
    }

    public function find(string $key): Model
    {
        return $this->lookup(WorkOrder::class, $key, ['variant.item', 'store']);
    }

    /**
     * @param  WorkOrder  $order
     */
    public function document(Model $order): Document
    {
        $unit = $order->variant?->item?->unit;
        $qty = fn ($value) => trim(InsufficientStock::format($value).' '.$unit);

        return Document::make('work_order', __('production::production.print_work_order'))
            ->number($order->number)
            ->meta(__('production::production.print_start'), $order->start_date?->format('d M Y'))
            ->meta(__('production::production.print_due'), $order->due_date?->format('d M Y'))
            ->meta(__('production::production.print_line'), $order->line)
            ->meta(__('production::production.print_status'), $order->status?->label())
            ->party(__('production::production.print_product'), $order->variant?->displayName(), [
                __('production::production.print_quantity').': '.$qty($order->quantity),
                $order->daily_target ? __('production::production.print_daily_target').': '.$qty($order->daily_target) : null,
                $order->store ? __('production::production.print_store').': '.$order->store->name : null,
                $order->sales_order_id && $order->salesOrder() ? __('production::production.print_for_order').': '.$order->salesOrder()->first()?->number : null,
            ])
            ->table([['#'], [__('production::production.print_stage')], [__('production::production.print_done'), 'right'], [__('production::production.print_remaining'), 'right'], [__('production::production.print_checked')]],
                collect((array) $order->stages)->values()->map(fn ($stage, $i) => [
                    $i + 1,
                    is_array($stage) ? ($stage['name'] ?? '') : (string) $stage,
                    $qty($order->count($i)),
                    $qty(max(0, $order->quantity - $order->count($i))),
                    '',
                ])->all())
            ->total(__('production::production.print_finished'), $qty($order->finished()), strong: true)
            ->total(__('production::production.print_rejected'), (float) $order->rejected > 0 ? $qty($order->rejected) : null)
            ->notes($order->notes)
            ->watermark(match ($order->status) {
                WorkOrderStatus::Completed => __('production::production.print_watermark_completed'),
                WorkOrderStatus::Cancelled => __('production::production.print_watermark_cancelled'),
                default => null,
            })
            ->withSignature();
    }
}
