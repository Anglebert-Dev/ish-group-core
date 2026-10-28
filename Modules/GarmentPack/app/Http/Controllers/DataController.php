<?php

namespace Modules\GarmentPack\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Services\StyleService;
use Modules\Inventory\Services\StoreService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['garments' => ['style']],
            'module_labels' => ['garments' => 'Garments'],
            'controller_labels' => ['style' => 'Styles and bills of materials'],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [[
            'label' => 'garmentpack::garment.styles',
            'route' => 'garmentpack.styles.index',
            'active' => 'garmentpack.styles.*',
            'permission' => 'list garments.style',
            'order' => 10,
            'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
        ]];
    }

    /**
     * Bill of materials on a work order for a product that has a style.
     */
    public function work_order_panel(object $workOrder): ?string
    {
        $style = Style::query()->where('item_id', $workOrder->variant?->item_id)->first();

        if (! $style || ! AuthHelper::can('read garments.style')) {
            return null;
        }

        return view('garmentpack::styles.work-order-panel', [
            'style' => $style,
            'workOrder' => $workOrder,
            'rows' => app(StyleService::class)->requirements($style, $workOrder),
            'stores' => app(StoreService::class)->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
        ])->render();
    }
}
