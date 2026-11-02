<?php

namespace Modules\SalesOrders\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;

/**
 * The order confirmation sent to the customer.
 */
class SalesOrderPrintable extends Printable
{
    public function label(): string
    {
        return __('salesorders::orders.print_order');
    }

    public function permission(): string
    {
        return 'read sales.order';
    }

    public function find(string $key): Model
    {
        return $this->lookup(SalesOrder::class, $key, ['customer', 'lines.variant.item']);
    }

    /**
     * @param  SalesOrder  $order
     */
    public function document(Model $order): Document
    {
        $document = Document::make('sales_order', __('salesorders::orders.print_order'))
            ->number($order->number)
            ->meta(__('salesorders::orders.print_date'), $order->order_date?->format('d M Y'))
            ->meta(__('salesorders::orders.print_due'), $order->due_date?->format('d M Y'))
            ->meta(__('salesorders::orders.print_your_reference'), $order->customer_reference)
            ->customer(__('salesorders::orders.print_customer'), $order->customer, delivery: true)
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right'], [__('inventory::inventory.line_price'), 'right'], [__('inventory::inventory.line_total'), 'right']],
                $order->lines->sortBy('position')->values()->map(fn ($line, $i) => [
                    $i + 1,
                    trim($line->variant?->displayName().($line->description ? "\n".$line->description : '')),
                    trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
                    money($line->unit_price),
                    money($line->line_total),
                ])->all())
            ->total(__('inventory::inventory.subtotal'), money($order->subtotal));

        if ((float) $order->discount > 0) {
            $document->total(__('inventory::inventory.discount'), '− '.money($order->discount));
        }

        return $document
            ->total(__('inventory::inventory.total'), money($order->total), strong: true)
            ->notes($order->notes)
            ->watermark($order->status === OrderStatus::Cancelled ? __('salesorders::orders.print_watermark_cancelled') : null)
            ->withSignature();
    }
}
