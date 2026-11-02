<?php

namespace Modules\Purchasing\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Purchasing\Models\PurchaseReceipt;

/**
 * Goods received note.
 */
class PurchaseReceiptPrintable extends Printable
{
    public function label(): string
    {
        return __('purchasing::purchasing.print_receipt');
    }

    public function permission(): string
    {
        return 'read purchasing.receipt';
    }

    public function find(string $key): Model
    {
        return $this->lookup(PurchaseReceipt::class, $key, ['supplier', 'purchaseOrder', 'lines.variant.item']);
    }

    /**
     * @param  PurchaseReceipt  $receipt
     */
    public function document(Model $receipt): Document
    {
        return PurchaseOrderPrintable::supplier(Document::make('purchase_receipt', __('purchasing::purchasing.print_receipt')), __('purchasing::purchasing.print_supplier'), $receipt->supplier)
            ->number($receipt->number)
            ->meta(__('purchasing::purchasing.print_received_on'), $receipt->received_on?->format('d M Y'))
            ->meta(__('purchasing::purchasing.print_order_ref'), $receipt->purchaseOrder?->number)
            ->meta(__('purchasing::purchasing.print_supplier_ref'), $receipt->supplier_reference)
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right'], [__('purchasing::purchasing.print_unit_cost'), 'right'], [__('inventory::inventory.line_total'), 'right']],
                $receipt->lines->values()->map(fn ($line, $i) => [
                    $i + 1,
                    $line->variant?->displayName(),
                    trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
                    money($line->unit_cost),
                    money(round((float) $line->quantity * (float) $line->unit_cost, 2)),
                ])->all())
            ->total(__('purchasing::purchasing.print_value'), money($receipt->value), strong: true)
            ->notes($receipt->note)
            ->section(__('purchasing::purchasing.print_checked_by'), ['________________________________'])
            ->withSignature();
    }
}
