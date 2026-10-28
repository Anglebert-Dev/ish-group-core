<?php

namespace Modules\Delivery\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Delivery\Models\DeliveryNote;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['delivery' => ['note']],
            'module_labels' => ['delivery' => 'Delivery'],
            'controller_labels' => ['note' => 'Delivery notes'],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [[
            'label' => 'delivery::delivery.notes',
            'route' => 'delivery.notes.index',
            'active' => 'delivery.notes.*',
            'permission' => 'list delivery.note',
            'order' => 15,
            'icon' => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
        ]];
    }

    public function sales_order_panel(object $salesOrder): ?string
    {
        if (! AuthHelper::can('list delivery.note')) {
            return null;
        }

        return view('delivery::notes.sales-order-panel', [
            'order' => $salesOrder,
            'notes' => DeliveryNote::query()->where('sales_order_id', $salesOrder->id)->latest('id')->get(),
        ])->render();
    }
}
