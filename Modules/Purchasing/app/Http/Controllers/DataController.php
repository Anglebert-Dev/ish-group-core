<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Purchasing\Enums\PurchaseStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Services\PurchasingService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['purchasing' => ['supplier', 'order', 'receipt', 'payment']],
            'module_labels' => ['purchasing' => 'Purchasing'],
            'controller_labels' => [
                'supplier' => 'Suppliers',
                'order' => 'Purchase orders (approve = send, delete = cancel)',
                'receipt' => 'Goods received',
                'payment' => 'Supplier payments (delete = reverse)',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'purchasing::purchasing.orders',
                'route' => 'purchasing.orders.index',
                'active' => 'purchasing.orders.*',
                'permission' => 'list purchasing.order',
                'order' => 10,
                'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            ],
            [
                'label' => 'purchasing::purchasing.reorder',
                'route' => 'purchasing.reorder',
                'active' => 'purchasing.reorder',
                'permission' => 'list purchasing.order',
                'order' => 15,
                'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            ],
            [
                'label' => 'purchasing::purchasing.suppliers',
                'route' => 'purchasing.suppliers.index',
                'active' => 'purchasing.suppliers.*',
                'permission' => 'list purchasing.supplier',
                'order' => 20,
                'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            ],
        ];
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list purchasing.order')) {
            return [];
        }

        return [
            [
                'label' => __('purchasing::purchasing.dashboard_reorder'),
                'value' => app(PurchasingService::class)->reorderList()->count(),
                'href' => route('purchasing.reorder'),
            ],
            [
                'label' => __('purchasing::purchasing.dashboard_awaiting'),
                'value' => PurchaseOrder::query()->whereIn('status', [PurchaseStatus::Ordered->value, PurchaseStatus::PartlyReceived->value])->count(),
                'href' => route('purchasing.orders.index', ['status' => 'ordered']),
            ],
        ];
    }
}
