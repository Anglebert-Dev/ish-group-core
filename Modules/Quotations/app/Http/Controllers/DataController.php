<?php

namespace Modules\Quotations\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Models\Customer\Customer;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Models\Quotation;
use Modules\Quotations\Services\QuotationService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['quotations' => ['quote', 'deposit']],
            'module_labels' => ['quotations' => 'Quotations'],
            'controller_labels' => [
                'quote' => 'Quotes and samples (approve = convert to order)',
                'deposit' => 'Deposits (delete = reverse)',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [[
            'label' => 'quotations::quotations.quotes',
            'route' => 'quotations.quotes.index',
            'active' => 'quotations.quotes.*',
            'permission' => 'list quotations.quote',
            'order' => 5,
            'icon' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z',
        ]];
    }

    public function customer_links(Customer $customer): int
    {
        return Quotation::query()->where('customer_id', $customer->id)->count();
    }

    /**
     * Deposits taken on the quote become payments on the order's invoice.
     */
    public function invoice_created(object $invoice): void
    {
        app(QuotationService::class)->applyDeposits($invoice);
    }

    public function sales_order_panel(object $salesOrder): ?string
    {
        $quote = Quotation::query()->where('sales_order_id', $salesOrder->id)->first();

        if (! $quote || ! AuthHelper::can('read quotations.quote')) {
            return null;
        }

        return '<div class="bg-white border border-line rounded-xl p-5 text-sm">'
            .e(__('quotations::quotations.from_quote')).' <a class="font-semibold text-brand" href="'.e(route('quotations.quotes.show', $quote)).'">'.e($quote->number).'</a>'
            .($quote->depositTotal() > 0 ? ' · '.e(__('quotations::quotations.deposits_total', ['amount' => money($quote->depositTotal())])) : '')
            .'</div>';
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list quotations.quote')) {
            return [];
        }

        return [[
            'label' => __('quotations::quotations.dashboard_follow_up'),
            'value' => Quotation::query()->where('status', QuoteStatus::Open->value)->whereDate('follow_up_date', '<=', now()->toDateString())->count(),
            'href' => route('quotations.quotes.index', ['follow_up' => 1]),
        ]];
    }
}
