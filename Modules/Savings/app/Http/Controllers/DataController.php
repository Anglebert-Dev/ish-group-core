<?php

namespace Modules\Savings\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Savings\Models\Account;
use Modules\Savings\Services\Planning;
use Modules\Savings\Support\Money;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['savings' => ['overview', 'account', 'transaction', 'category', 'budget', 'planning', 'obligation', 'goal', 'reconciliation', 'report', 'data', 'setting']],
            'module_labels' => ['savings' => 'Savings (personal finance)'],
            'controller_labels' => [
                'overview' => 'Overview and forecast',
                'account' => 'Accounts',
                'transaction' => 'Transactions (delete = reverse)',
                'category' => 'Categories',
                'budget' => 'Budgets',
                'planning' => 'Expected, recurring and committed money',
                'obligation' => 'Owed, debts and money to put back (delete = write off)',
                'goal' => 'Goals',
                'reconciliation' => 'Reconciliation',
                'report' => 'Reports',
                'data' => 'Import, backup and restore',
                'setting' => 'Finance settings',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        $items = [
            ['overview', 'savings.overview', 'savings.overview', 'list savings.overview', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['transactions', 'savings.transactions.index', 'savings.transactions.*', 'list savings.transaction', 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
            ['accounts', 'savings.accounts.index', 'savings.accounts.*', 'list savings.account', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
            ['budgets', 'savings.budgets.index', 'savings.budgets.*', 'list savings.budget', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['planning', 'savings.planning.index', 'savings.planning.*', 'list savings.planning', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['obligations', 'savings.obligations.index', 'savings.obligations.*', 'list savings.obligation', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['goals', 'savings.goals.index', 'savings.goals.*', 'list savings.goal', 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
            ['forecast', 'savings.forecast', 'savings.forecast', 'list savings.overview', 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'],
            ['reconcile', 'savings.reconcile.index', 'savings.reconcile.*', 'list savings.reconciliation', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['reports', 'savings.reports', 'savings.reports*', 'list savings.report', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['categories', 'savings.categories.index', 'savings.categories.*', 'list savings.category', 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
            ['data', 'savings.data.index', 'savings.data.*', 'list savings.data', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
            ['settings', 'savings.settings.edit', 'savings.settings.*', 'update savings.setting', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
        ];

        return collect($items)->map(fn ($i, $order) => [
            'label' => 'savings::savings.'.$i[0], 'route' => $i[1], 'active' => $i[2], 'permission' => $i[3], 'order' => ($order + 1) * 10, 'icon' => $i[4],
        ])->all();
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list savings.overview')) {
            return [];
        }

        $planning = app(Planning::class);
        $currency = Money::baseCurrency();
        $available = Account::query()->where('status', 'active')->where('is_liquid', true)->where('currency', $currency)->get()
            ->sum(fn (Account $a) => $planning->available($a)['available']);

        return [[
            'label' => __('savings::savings.dashboard_available'),
            'value' => Money::format((int) $available, $currency),
            'href' => route('savings.overview'),
        ]];
    }
}
