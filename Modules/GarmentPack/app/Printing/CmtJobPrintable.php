<?php

namespace Modules\GarmentPack\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\GarmentPack\Models\CmtJob;

/**
 * The order sent with work to a subcontractor (cut-make-trim).
 */
class CmtJobPrintable extends Printable
{
    public function label(): string
    {
        return __('garmentpack::garment.print_cmt');
    }

    public function permission(): string
    {
        return 'list garments.cmt';
    }

    public function find(string $key): Model
    {
        return $this->lookup(CmtJob::class, $key, ['workOrder.variant.item']);
    }

    /**
     * @param  CmtJob  $job
     */
    public function document(Model $job): Document
    {
        $unit = $job->workOrder?->variant?->item?->unit;
        $qty = fn ($value) => trim(rtrim(rtrim(number_format((float) $value, 3), '0'), '.').' '.$unit);

        return Document::make('cmt_job', __('garmentpack::garment.print_cmt'))
            ->number($job->number)
            ->meta(__('garmentpack::garment.print_sent_on'), $job->sent_on?->format('d M Y'))
            ->meta(__('garmentpack::garment.print_due_on'), $job->due_on?->format('d M Y'))
            ->meta(__('garmentpack::garment.print_work_order'), $job->workOrder?->number)
            ->party(__('garmentpack::garment.print_subcontractor'), $job->subcontractor, [$job->phone])
            ->table([[__('garmentpack::garment.print_product')], [__('garmentpack::garment.print_stage')], [__('garmentpack::garment.print_sent'), 'right'], [__('garmentpack::garment.print_returned'), 'right'], [__('garmentpack::garment.print_rate'), 'right']], [[
                $job->workOrder?->variant?->displayName(),
                $job->stage_name,
                $qty($job->sent_quantity),
                $qty($job->returned_quantity),
                money($job->rate),
            ]])
            ->total(__('garmentpack::garment.print_outstanding'), $qty($job->outstanding()))
            ->total(__('garmentpack::garment.print_owed'), money($job->owed()), strong: true)
            ->notes($job->notes)
            ->section(__('garmentpack::garment.print_received_by'), ['________________________________'])
            ->withSignature();
    }
}
