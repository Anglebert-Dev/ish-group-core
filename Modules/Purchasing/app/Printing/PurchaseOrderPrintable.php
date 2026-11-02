<?php

namespace Modules\Purchasing\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Purchasing\Enums\PurchaseStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\Supplier;

class PurchaseOrderPrintable extends Printable
{
    public function label(): string
    {
        return __('purchasing::purchasing.print_order');
    }

    public function permission(): string
    {
        return 'read purchasing.order';
    }

    public function find(string $key): Model
    {
        return $this->lookup(PurchaseOrder::class, $key, ['supplier', 'store', 'lines.variant.item']);
    }

    /**
     * @param  PurchaseOrder  $order
     */
    public function document(Model $order): Document
    {
        return self::supplier(Document::make('purchase_order', __('purchasing::purchasing.print_order')), __('purchasing::purchasing.print_supplier'), $order->supplier)
            ->number($order->number)
            ->meta(__('purchasing::purchasing.print_date'), $order->order_date?->format('d M Y'))
            ->meta(__('purchasing::purchasing.print_expected'), $order->expected_date?->format('d M Y'))
            ->party(__('purchasing::purchasing.print_deliver_to'), $order->store?->name, [$order->store?->address ?? null])
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right'], [__('purchasing::purchasing.print_unit_cost'), 'right'], [__('inventory::inventory.line_total'), 'right']],
                $order->lines->sortBy('position')->values()->map(fn ($line, $i) => [
                    $i + 1,
                    trim($line->variant?->displayName().($line->description ? "\n".$line->description : '')),
                    trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
                    money($line->unit_cost),
                    money($line->line_total),
                ])->all())
            ->total(__('inventory::inventory.total'), money($order->total), strong: true)
            ->notes($order->notes)
            ->watermark(match ($order->status) {
                PurchaseStatus::Draft => __('purchasing::purchasing.print_watermark_draft'),
                PurchaseStatus::Cancelled => __('purchasing::purchasing.print_watermark_cancelled'),
                default => null,
            })
            ->withSignature();
    }

    /** A supplier as a party (shared by the purchasing documents). */
    public static function supplier(Document $document, string $label, ?Supplier $supplier): Document
    {
        return $supplier ? $document->party($label, $supplier->name, [
            $supplier->contact_person,
            $supplier->address,
            implode(' · ', array_filter([$supplier->phone, $supplier->email])) ?: null,
            $supplier->tax_id ? __('body.brand_tax_id_short').' '.$supplier->tax_id : null,
        ]) : $document;
    }
}
