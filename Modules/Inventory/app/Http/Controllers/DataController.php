<?php

namespace Modules\Inventory\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StoreService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'inventory' => ['item', 'store', 'movement', 'setting'],
            ],
            'module_labels' => ['inventory' => 'Inventory'],
            'controller_labels' => [
                'item' => 'Items',
                'store' => 'Stores',
                'movement' => 'Stock changes and history',
                'setting' => 'Inventory settings',
            ],
        ];
    }

    /**
     * The "Inventory" section of the staff sidebar.
     */
    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'inventory::inventory.items',
                'route' => 'inventory.items.index',
                'active' => 'inventory.items.*',
                'permission' => 'list inventory.item',
                'order' => 10,
                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            ],
            [
                'label' => 'inventory::inventory.stores',
                'route' => 'inventory.stores.index',
                'active' => 'inventory.stores.*',
                'permission' => 'list inventory.store',
                'order' => 20,
                'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6',
            ],
            [
                'label' => 'inventory::inventory.movements',
                'route' => 'inventory.movements.index',
                'active' => 'inventory.movements.*',
                'permission' => 'list inventory.movement',
                'order' => 30,
                'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
            ],
            [
                'label' => 'inventory::inventory.settings',
                'route' => 'inventory.settings.edit',
                'active' => 'inventory.settings.*',
                'permission' => 'update inventory.setting',
                'order' => 40,
                'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
            ],
        ];
    }

    /**
     * Every new business starts with a default store.
     */
    public function after_business_created(array $args): void
    {
        app(StoreService::class)->default($args['business']);
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list inventory.item')) {
            return [];
        }

        $items = app(ItemService::class);

        return [
            [
                'label' => __('inventory::inventory.dashboard_items'),
                'value' => $items->filtered([])->count(),
                'href' => route('inventory.items.index'),
            ],
            [
                'label' => __('inventory::inventory.dashboard_low'),
                'value' => $items->lowStockCount(),
                'href' => route('inventory.items.index', ['stock' => 'low']),
            ],
        ];
    }
}
