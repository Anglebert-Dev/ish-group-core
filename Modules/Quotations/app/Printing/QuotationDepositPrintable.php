<?php

namespace Modules\Quotations\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Quotations\Models\QuotationDeposit;

class QuotationDepositPrintable extends Printable
{
    public function label(): string
    {
        return __('quotations::quotations.print_deposit_receipt');
    }

    public function permission(): string
    {
        return 'read quotations.deposit';
    }

    public function find(string $key): Model
    {
        return $this->lookup(QuotationDeposit::class, $key, ['quotation.customer']);
    }

    /**
     * @param  QuotationDeposit  $deposit
     */
    public function document(Model $deposit): Document
    {
        $quote = $deposit->quotation;

        return Document::make('quotation_deposit', __('quotations::quotations.print_deposit_receipt'))
            ->number($quote?->number)
            ->meta(__('quotations::quotations.print_received_on'), $deposit->paid_on?->format('d M Y'))
            ->meta(__('quotations::quotations.print_method'), $deposit->method ? __('quotations::quotations.method_'.$deposit->method) : null)
            ->meta(__('quotations::quotations.print_reference'), $deposit->reference)
            ->customer(__('quotations::quotations.print_received_from'), $quote?->customer)
            ->table([[__('quotations::quotations.print_description')], [__('inventory::inventory.line_total'), 'right']], [
                [__('quotations::quotations.print_deposit_for', ['number' => $quote?->number]), money($deposit->amount)],
            ])
            ->total(__('quotations::quotations.print_amount_received'), money($deposit->amount), strong: true)
            ->total(__('quotations::quotations.print_quote_total'), $quote ? money($quote->total) : null)
            ->watermark($deposit->isReversed() ? __('quotations::quotations.print_watermark_reversed') : null)
            ->withSignature();
    }
}
