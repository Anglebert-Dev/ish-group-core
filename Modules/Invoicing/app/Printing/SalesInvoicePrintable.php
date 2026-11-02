<?php

namespace Modules\Invoicing\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Invoicing\Enums\InvoiceStatus;
use Modules\Invoicing\Models\SalesInvoice;

/**
 * An invoice; a cancelled one prints as its credit note.
 */
class SalesInvoicePrintable extends Printable
{
    public function label(): string
    {
        return __('invoicing::invoicing.pdf_invoice');
    }

    public function permission(): string
    {
        return 'read invoicing.invoice';
    }

    public function find(string $key): Model
    {
        return SalesInvoice::query()->where('uuid', $key)->with(['lines.variant.item', 'customer'])->firstOrFail();
    }

    /**
     * @param  SalesInvoice  $invoice
     */
    public function document(Model $invoice): Document
    {
        $cancelled = $invoice->status === InvoiceStatus::Cancelled;
        $rows = $invoice->lines->values()->map(fn ($line, $i) => [
            $i + 1,
            trim($line->variant?->displayName().($line->description ? "\n".$line->description : '')),
            trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
            money($line->unit_price),
            money($line->line_total),
        ])->all();

        $document = Document::make('sales_invoice', $cancelled ? __('invoicing::invoicing.pdf_credit_note') : __('invoicing::invoicing.pdf_invoice'))
            ->number($cancelled ? $invoice->credit_note_number : $invoice->number)
            ->meta(__('invoicing::invoicing.invoice_date'), $invoice->invoice_date?->format('d M Y'))
            ->meta(__('invoicing::invoicing.due_date'), $cancelled ? null : $invoice->due_date?->format('d M Y'))
            ->meta(__('invoicing::invoicing.pdf_for_invoice'), $cancelled ? $invoice->number : null)
            ->customer(__('invoicing::invoicing.pdf_bill_to'), $invoice->customer)
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right'], [__('inventory::inventory.line_price'), 'right'], [__('inventory::inventory.line_total'), 'right']], $rows)
            ->total(__('inventory::inventory.subtotal'), money($invoice->subtotal));

        if ((float) $invoice->discount > 0) {
            $document->total(__('inventory::inventory.discount'), '− '.money($invoice->discount));
        }

        if ((float) $invoice->tax > 0) {
            $document->total(__('invoicing::invoicing.tax_named', ['rate' => rtrim(rtrim(number_format($invoice->tax_rate, 3), '0'), '.')]), money($invoice->tax));
        }

        $document->total(__('inventory::inventory.total'), money($invoice->total), strong: true);

        if (! $cancelled) {
            $document->total(__('invoicing::invoicing.paid'), money($invoice->paid))
                ->total(__('invoicing::invoicing.pdf_page_total'), money($invoice->balance()), strong: true);
        }

        return $document
            ->notes($invoice->notes)
            ->watermark(match ($invoice->status) {
                InvoiceStatus::Paid => __('invoicing::invoicing.watermark_paid'),
                InvoiceStatus::Cancelled => __('invoicing::invoicing.watermark_cancelled'),
                default => null,
            })
            ->withPayment(! $cancelled && $invoice->balance() > 0)
            ->withSignature();
    }

    public function filename(Model $invoice): string
    {
        return $invoice->status === InvoiceStatus::Cancelled && $invoice->credit_note_number ? $invoice->credit_note_number : $invoice->number;
    }
}
