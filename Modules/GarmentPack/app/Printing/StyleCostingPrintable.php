<?php

namespace Modules\GarmentPack\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Services\StyleService;

/**
 * A style's bill of materials and cost per piece.
 */
class StyleCostingPrintable extends Printable
{
    public function label(): string
    {
        return __('garmentpack::garment.print_costing');
    }

    public function permission(): string
    {
        return 'read garments.style';
    }

    public function find(string $key): Model
    {
        return $this->lookup(Style::class, $key, ['item', 'materials.variant.item']);
    }

    /**
     * @param  Style  $style
     */
    public function document(Model $style): Document
    {
        $costing = app(StyleService::class)->costing($style);

        return Document::make('style_costing', __('garmentpack::garment.print_costing'))
            ->number($style->code)
            ->meta(__('garmentpack::garment.print_style'), $style->name)
            ->meta(__('garmentpack::garment.print_product'), $style->item?->name)
            ->table([[__('garmentpack::garment.print_material')], [__('garmentpack::garment.print_per_piece'), 'right'], [__('garmentpack::garment.print_waste'), 'right'], [__('garmentpack::garment.print_unit_cost'), 'right'], [__('garmentpack::garment.print_cost'), 'right']],
                collect($costing['lines'])->map(fn (array $line) => [
                    trim($line['material']->variant?->displayName().($line['material']->note ? "\n".$line['material']->note : '')),
                    rtrim(rtrim(number_format($line['per_piece'], 4), '0'), '.').' '.$line['material']->variant?->item?->unit,
                    rtrim(rtrim(number_format((float) $line['material']->waste_percent, 2), '0'), '.').'%',
                    $line['material']->variant?->cost_price !== null ? money($line['material']->variant->cost_price) : '—',
                    money($line['cost']),
                ])->all())
            ->total(__('garmentpack::garment.print_materials'), money($costing['materials']))
            ->total(__('garmentpack::garment.print_labour'), money($costing['labour']))
            ->total(__('garmentpack::garment.print_overhead'), money($costing['overhead']))
            ->total(__('garmentpack::garment.print_cost_per_piece'), money($costing['total']), strong: true)
            ->notes($style->description);
    }
}
