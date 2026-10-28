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
            'access_modules' => ['garments' => ['style', 'worker', 'cmt']],
            'module_labels' => ['garments' => 'Garments'],
            'controller_labels' => [
                'style' => 'Styles and bills of materials',
                'worker' => 'Workers and piece-rate pay (approve = mark paid)',
                'cmt' => 'Subcontracting (CMT)',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'garmentpack::garment.workers',
                'route' => 'garmentpack.workers.index',
                'active' => 'garmentpack.workers.*',
                'permission' => 'list garments.worker',
                'order' => 20,
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            ],
            [
                'label' => 'garmentpack::garment.cmt',
                'route' => 'garmentpack.cmt.index',
                'active' => 'garmentpack.cmt.*',
                'permission' => 'list garments.cmt',
                'order' => 30,
                'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
            ],
            [
            'label' => 'garmentpack::garment.styles',
            'route' => 'garmentpack.styles.index',
            'active' => 'garmentpack.styles.*',
            'permission' => 'list garments.style',
            'order' => 10,
            'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
            ],
        ];
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
            'workers' => \Modules\GarmentPack\Models\Worker::query()->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'pieceWork' => \Modules\GarmentPack\Models\PieceWork::query()->where('work_order_id', $workOrder->id)->with('worker')->latest('id')->limit(10)->get(),
            'cmtJobs' => \Modules\GarmentPack\Models\CmtJob::query()->where('work_order_id', $workOrder->id)->latest('id')->get(),
            'bundles' => \Modules\GarmentPack\Models\Bundle::query()->where('work_order_id', $workOrder->id)->orderBy('number')->get(),
        ])->render();
    }

    /**
     * Tailoring measurements on the customer page.
     */
    public function customer_panel(\App\Models\Customer\Customer $customer): ?string
    {
        if (! AuthHelper::can('read customers.customer')) {
            return null;
        }

        return view('garmentpack::workshop.measurements', [
            'customer' => $customer,
            'measurements' => \Modules\GarmentPack\Models\Measurement::query()->where('customer_id', $customer->id)->latest('id')->get(),
            'fields' => (array) app(\App\Support\Settings\BusinessSettings::class)->get('garments.measurement_fields', config('garmentpack.measurement_fields')),
        ])->render();
    }
}
