<?php

namespace Modules\Invoicing\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Invoicing\Models\SalesPayment;

/**
 * A receipt for one customer payment.
 */
class SalesPaymentPrintable extends Printable
{
    public function label(): string
    {
        return __('invoicing::invoicing.pdf_receipt');
    }

    public function permission(): string
    {
        return 'read invoicing.payment';
    }

    public function find(string $key): Model
    {
        return SalesPayment::query()->where('uuid', $key)->with('invoice.customer')->firstOrFail();
    }

    /**
     * @param  SalesPayment  $payment
     */
    public function document(Model $payment): Document
    {
        $invoice = $payment->invoice;

        return Document::make('sales_payment', __('invoicing::invoicing.pdf_receipt'))
            ->number($payment->number)
            ->meta(__('invoicing::invoicing.pdf_received_on'), $payment->paid_on?->format('d M Y'))
            ->meta(__('invoicing::invoicing.pdf_method'), $payment->method?->label())
            ->meta(__('invoicing::invoicing.pdf_reference'), $payment->reference)
            ->customer(__('invoicing::invoicing.pdf_received_from'), $invoice?->customer)
            ->table([[__('invoicing::invoicing.pdf_description')], [__('inventory::inventory.line_total'), 'right']], [
                [__('invoicing::invoicing.pdf_payment_for', ['number' => $invoice?->number]), money($payment->amount)],
            ])
            ->total(__('invoicing::invoicing.pdf_amount_received'), money($payment->amount), strong: true)
            ->total(__('invoicing::invoicing.pdf_invoice_total'), $invoice ? money($invoice->total) : null)
            ->total(__('invoicing::invoicing.pdf_balance_after'), $invoice ? money($invoice->balance()) : null)
            ->notes($payment->note)
            ->watermark($payment->isReversed() ? __('invoicing::invoicing.watermark_reversed') : null)
            ->withSignature();
    }
}
