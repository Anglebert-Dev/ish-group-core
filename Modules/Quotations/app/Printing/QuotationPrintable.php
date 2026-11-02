<?php

namespace Modules\Quotations\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Models\Quotation;

class QuotationPrintable extends Printable
{
    public function label(): string
    {
        return __('quotations::quotations.print_quotation');
    }

    public function permission(): string
    {
        return 'read quotations.quote';
    }

    public function find(string $key): Model
    {
        return $this->lookup(Quotation::class, $key, ['customer', 'lines.variant.item', 'deposits']);
    }

    /**
     * @param  Quotation  $quote
     */
    public function document(Model $quote): Document
    {
        $deposits = (float) $quote->deposits->whereNull('reversed_at')->sum('amount');

        $document = Document::make('quotation', $quote->is_sample ? __('quotations::quotations.print_sample_request') : __('quotations::quotations.print_quotation'))
            ->number($quote->number)
            ->meta(__('quotations::quotations.print_date'), $quote->quote_date?->format('d M Y'))
            ->meta(__('quotations::quotations.print_valid_until'), $quote->valid_until?->format('d M Y'))
            ->customer(__('quotations::quotations.print_for'), $quote->customer)
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right'], [__('inventory::inventory.line_price'), 'right'], [__('inventory::inventory.line_total'), 'right']],
                $quote->lines->sortBy('position')->values()->map(fn ($line, $i) => [
                    $i + 1,
                    trim($line->variant?->displayName().($line->description ? "\n".$line->description : '')),
                    trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
                    money($line->unit_price),
                    money($line->line_total),
                ])->all())
            ->total(__('inventory::inventory.subtotal'), money($quote->subtotal));

        if ((float) $quote->discount > 0) {
            $document->total(__('inventory::inventory.discount'), '− '.money($quote->discount));
        }

        $document->total(__('inventory::inventory.total'), money($quote->total), strong: true);

        if ($deposits > 0) {
            $document->total(__('quotations::quotations.print_deposits'), money($deposits))
                ->total(__('quotations::quotations.print_balance'), money(max(0, (float) $quote->total - $deposits)), strong: true);
        }

        return $document
            ->notes($quote->notes)
            ->watermark(match ($quote->status) {
                QuoteStatus::Rejected => __('quotations::quotations.print_watermark_rejected'),
                QuoteStatus::Converted => __('quotations::quotations.print_watermark_converted'),
                default => null,
            })
            ->withPayment($quote->status === QuoteStatus::Open || $quote->status === QuoteStatus::Accepted)
            ->withSignature();
    }
}
