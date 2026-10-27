<?php

namespace Modules\SalesOrders\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Models\Customer\Customer;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;
use Modules\SalesOrders\Services\OrderService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['sales' => ['order']],
            'module_labels' => ['sales' => 'Sales'],
            'controller_labels' => ['order' => 'Sales orders (approve = confirm and deliver, delete = cancel)'],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [[
            'label' => 'salesorders::orders.orders',
            'route' => 'salesorders.orders.index',
            'active' => 'salesorders.orders.*',
            'permission' => 'list sales.order',
            'order' => 10,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ]];
    }

    /**
     * A customer with orders can't be deleted.
     */
    public function customer_links(Customer $customer): int
    {
        return SalesOrder::query()->where('customer_id', $customer->id)->count();
    }

    public function customer_panel(Customer $customer): ?string
    {
        if (! AuthHelper::can('list sales.order')) {
            return null;
        }

        return view('salesorders::orders.customer-panel', [
            'customer' => $customer,
            'orders' => SalesOrder::query()->where('customer_id', $customer->id)->latest('order_date')->latest('id')->limit(8)->get(),
        ])->render();
    }

    /**
     * Stock promised on open orders.
     */
    public function inventory_reserved(array $variantIds): array
    {
        return app(OrderService::class)->reserved($variantIds);
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list sales.order')) {
            return [];
        }

        $open = SalesOrder::query()->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Confirmed->value, OrderStatus::InProduction->value, OrderStatus::Ready->value]);

        return [
            [
                'label' => __('salesorders::orders.dashboard_open'),
                'value' => (clone $open)->count(),
                'href' => route('salesorders.orders.index'),
            ],
            [
                'label' => __('salesorders::orders.dashboard_overdue'),
                'value' => (clone $open)->whereDate('due_date', '<', now()->toDateString())->count(),
                'href' => route('salesorders.orders.index', ['overdue' => 1]),
            ],
        ];
    }

    public function dashboard_quick_actions(): array
    {
        return [[
            'label' => __('salesorders::orders.add'),
            'route' => 'salesorders.orders.create',
            'permission' => 'create sales.order',
        ]];
    }
}
