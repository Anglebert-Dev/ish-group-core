<?php

namespace App\Support\Printing;

/**
 * The invoice shown in the designer's live preview: realistic enough to judge
 * every part of a layout (parties, table, totals, payment, signature).
 */
final class SampleDocument
{
    public static function make(): Document
    {
        return Document::make('sample', __('body.print_sample_title'))
            ->number('INV-'.now()->year.'-00042')
            ->meta(__('body.print_sample_date'), now()->format('d M Y'))
            ->meta(__('body.print_sample_due'), now()->addDays(14)->format('d M Y'))
            ->meta(__('body.print_sample_reference'), 'SO-'.now()->year.'-00017')
            ->party(__('body.print_sample_bill_to'), 'Kigali Sun Garments Ltd', ['KG 11 Ave, Kigali', 'TIN 109876543'])
            ->party(__('body.print_sample_deliver_to'), 'Warehouse 2', ['Kicukiro, Gikondo'])
            ->table([['#'], [__('body.print_sample_item')], [__('body.print_sample_qty'), 'right'], [__('body.print_sample_price'), 'right'], [__('body.print_sample_amount'), 'right']], [
                ['1', 'Polo shirt, navy — M', '120', '6,500', '780,000'],
                ['2', 'Polo shirt, navy — L', '80', '6,500', '520,000'],
                ['3', 'Embroidery, left chest', '200', '800', '160,000'],
            ])
            ->total(__('body.print_sample_subtotal'), '1,460,000')
            ->total(__('body.print_sample_tax'), '262,800')
            ->total(__('body.print_sample_total'), '1,722,800', strong: true)
            ->notes(__('body.print_sample_notes'))
            ->watermark(__('body.print_sample_watermark'))
            ->withPayment()
            ->withSignature();
    }
}
