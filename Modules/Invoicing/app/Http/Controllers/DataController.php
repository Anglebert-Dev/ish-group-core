<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Models\Customer\Customer;
use Modules\Invoicing\Enums\InvoiceStatus;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Invoicing\Services\InvoiceService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['invoicing' => ['invoice', 'payment', 'setting']],
            'module_labels' => ['invoicing' => 'Invoicing'],
            'controller_labels' => [
                'invoice' => 'Invoices (delete = cancel with credit note)',
                'payment' => 'Customer payments (delete = reverse)',
                'setting' => 'Invoice settings (tax, details)',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'invoicing::invoicing.invoices',
                'route' => 'invoicing.invoices.index',
                'active' => 'invoicing.invoices.*',
                'permission' => 'list invoicing.invoice',
                'order' => 20,
                'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
            ],
            [
                'label' => 'invoicing::invoicing.settings',
                'route' => 'invoicing.settings.edit',
                'active' => 'invoicing.settings.*',
                'permission' => 'update invoicing.setting',
                'order' => 90,
                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
            ],
        ];
    }

    public function customer_links(Customer $customer): int
    {
        return SalesInvoice::query()->where('customer_id', $customer->id)->count();
    }

    public function customer_panel(Customer $customer): ?string
    {
        if (! AuthHelper::can('list invoicing.invoice')) {
            return null;
        }

        return view('invoicing::invoices.customer-panel', [
            'customer' => $customer,
            'invoices' => SalesInvoice::query()->where('customer_id', $customer->id)->latest('invoice_date')->latest('id')->limit(8)->get(),
            'balance' => app(InvoiceService::class)->customerBalance($customer->id),
        ])->render();
    }

    public function sales_order_panel(object $salesOrder): ?string
    {
        if (! AuthHelper::can('list invoicing.invoice')) {
            return null;
        }

        return view('invoicing::invoices.sales-order-panel', [
            'salesOrder' => $salesOrder,
            'invoices' => SalesInvoice::query()->where('sales_order_id', $salesOrder->id)->latest('id')->get(),
        ])->render();
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list invoicing.invoice')) {
            return [];
        }

        $open = SalesInvoice::query()->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value]);

        return [
            [
                'label' => __('invoicing::invoicing.dashboard_owed'),
                'value' => money((float) (clone $open)->selectRaw('COALESCE(SUM(total - paid), 0) as owed')->value('owed')),
                'href' => route('invoicing.invoices.index', ['status' => 'open']),
            ],
            [
                'label' => __('invoicing::invoicing.dashboard_overdue'),
                'value' => (clone $open)->whereDate('due_date', '<', now()->toDateString())->count(),
                'href' => route('invoicing.invoices.index', ['overdue' => 1]),
            ],
        ];
    }

    public function dashboard_quick_actions(): array
    {
        return [[
            'label' => __('invoicing::invoicing.add'),
            'route' => 'invoicing.invoices.create',
            'permission' => 'create invoicing.invoice',
        ]];
    }
}
