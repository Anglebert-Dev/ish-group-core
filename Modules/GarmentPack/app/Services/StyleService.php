<?php

namespace Modules\GarmentPack\Services;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Models\StyleMaterial;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\Store;
use Modules\Production\Models\WorkOrder;
use Modules\Production\Services\WorkOrderService;

class StyleService extends BaseService
{
    public function __construct(protected WorkOrderService $workOrders)
    {
    }

    /**
     * @param  list<array{variant: \Modules\Inventory\Models\Variant, quantity: float, waste_percent: float, note: ?string}>  $materials
     */
    public function save(Style $style, array $data, array $materials): array
    {
        DB::transaction(function () use ($style, $data, $materials) {
            $style->fill($data)->save();
            $style->materials()->delete();

            foreach ($materials as $material) {
                $row = new StyleMaterial([
                    'variant_id' => $material['variant']->id,
                    'quantity' => $material['quantity'],
                    'waste_percent' => $material['waste_percent'],
                    'note' => $material['note'],
                ]);
                $row->business_id = $style->business_id;
                $row->style_id = $style->id;
                $row->save();
            }
        });

        return $this->success($style, __('garmentpack::garment.saved'));
    }

    /**
     * Cost of one piece: materials (with waste, at cost price) + labour + overhead.
     *
     * @return array{materials: float, labour: float, overhead: float, total: float, lines: list<array{material: StyleMaterial, per_piece: float, cost: float}>}
     */
    public function costing(Style $style): array
    {
        $style->loadMissing('materials.variant.item');
        $lines = $style->materials->map(fn (StyleMaterial $m) => [
            'material' => $m,
            'per_piece' => $m->perPiece(),
            'cost' => round($m->perPiece() * (float) $m->variant?->cost_price, 2),
        ])->all();
        $materials = round(array_sum(array_column($lines, 'cost')), 2);

        return [
            'materials' => $materials,
            'labour' => (float) $style->labour_cost,
            'overhead' => (float) $style->overhead_cost,
            'total' => round($materials + (float) $style->labour_cost + (float) $style->overhead_cost, 2),
            'lines' => $lines,
        ];
    }

    /**
     * What a work order needs from the bill of materials, and what was already issued.
     *
     * @return list<array{material: StyleMaterial, needed: float, issued: float, remaining: float}>
     */
    public function requirements(Style $style, WorkOrder $workOrder): array
    {
        $issued = Movement::query()
            ->where('reference_type', $workOrder->getMorphClass())->where('reference_id', $workOrder->id)
            ->where('type', 'production_out')
            ->selectRaw('variant_id, SUM(-quantity) as qty')->groupBy('variant_id')->pluck('qty', 'variant_id');

        return $style->materials()->with('variant.item')->get()->map(function (StyleMaterial $m) use ($workOrder, $issued) {
            $needed = round($m->perPiece() * $workOrder->quantity, 3);
            $done = (float) ($issued[$m->variant_id] ?? 0);

            return ['material' => $m, 'needed' => $needed, 'issued' => $done, 'remaining' => max(0, round($needed - $done, 3))];
        })->all();
    }

    /**
     * Issue everything still needed for the work order, from one store (all or nothing).
     */
    public function issueRemaining(Style $style, WorkOrder $workOrder, Store $store): array
    {
        $lines = collect($this->requirements($style, $workOrder))
            ->filter(fn ($row) => $row['remaining'] > 0)
            ->map(fn ($row) => ['variant' => $row['material']->variant, 'quantity' => $row['remaining']])
            ->values()->all();

        if ($lines === []) {
            return $this->failure(__('garmentpack::garment.nothing_to_issue'));
        }

        return $this->workOrders->issueMaterials($workOrder, $store, $lines, __('garmentpack::garment.issue_reason', ['style' => $style->code]));
    }
}
