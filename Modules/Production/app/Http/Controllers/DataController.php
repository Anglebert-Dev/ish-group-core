<?php

namespace Modules\Production\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Production\Enums\WorkOrderStatus;
use Modules\Production\Models\WorkOrder;
use Modules\Production\Services\WorkOrderService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['production' => ['work_order', 'entry', 'setting']],
            'module_labels' => ['production' => 'Production'],
            'controller_labels' => [
                'work_order' => 'Work orders (approve = close, delete = cancel)',
                'entry' => 'Production counts and materials',
                'setting' => 'Production stages',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'production::production.work_orders',
                'route' => 'production.work-orders.index',
                'active' => 'production.work-orders.*',
                'permission' => 'list production.work_order',
                'order' => 10,
                'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
            ],
            [
                'label' => 'production::production.settings',
                'route' => 'production.settings.edit',
                'active' => 'production.settings.*',
                'permission' => 'update production.setting',
                'order' => 20,
                'icon' => 'M4 6h16M4 12h16M4 18h7',
            ],
        ];
    }

    /**
     * Work orders on the sales order page.
     */
    public function sales_order_panel(object $salesOrder): ?string
    {
        if (! AuthHelper::can('list production.work_order')) {
            return null;
        }

        return view('production::work-orders.sales-order-panel', [
            'salesOrder' => $salesOrder,
            'workOrders' => WorkOrder::query()->where('sales_order_id', $salesOrder->id)->with('variant.item')->latest('id')->get(),
        ])->render();
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list production.work_order')) {
            return [];
        }

        $finishedToday = collect(app(WorkOrderService::class)->today())->sum('done');

        return [
            [
                'label' => __('production::production.dashboard_open'),
                'value' => WorkOrder::query()->whereIn('status', [WorkOrderStatus::Planned->value, WorkOrderStatus::InProgress->value])->count(),
                'href' => route('production.work-orders.index'),
            ],
            [
                'label' => __('production::production.dashboard_today'),
                'value' => \Modules\Inventory\Exceptions\InsufficientStock::format($finishedToday),
                'href' => route('production.work-orders.index'),
            ],
        ];
    }
}
