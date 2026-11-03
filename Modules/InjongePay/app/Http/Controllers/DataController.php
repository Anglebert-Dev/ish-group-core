<?php

namespace Modules\InjongePay\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'injongepay' => ['overview', 'wallet', 'payment', 'ledger', 'transfer', 'cashout', 'settlement_account', 'report', 'profile'],
                'injongepay_platform' => ['business', 'wallet', 'bank', 'fee', 'reconciliation', 'cashout', 'setting'],
            ],
            // Held only by the operator's staff (core PlatformAccess).
            'platform_modules' => ['injongepay_platform'],
            'module_labels' => [
                'injongepay' => 'Injonge Pay',
                'injongepay_platform' => 'Injonge Pay platform (operator only)',
            ],
            'controller_labels' => [
                'overview' => 'Overview',
                'wallet' => 'Merchant accounts (wallets)',
                'payment' => 'Payments received',
                'ledger' => 'Ledger',
                'transfer' => 'Transfers between wallets',
                'cashout' => 'Cash-outs to the bank',
                'settlement_account' => 'Settlement accounts',
                'report' => 'Reports',
                'profile' => 'Business details (contacts)',
                'business' => 'Businesses: KYC and fees',
                'bank' => 'GTBank: registration, sync and call log',
                'fee' => 'Fee rates and history',
                'reconciliation' => 'Reconciliation with GTBank',
                'setting' => 'Platform settings (webhook secret)',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        $icon = 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z';
        $gear = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z';
        $building = 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4';

        return [
            ['label' => 'injongepay::injongepay.menu_overview', 'route' => 'injongepay.overview', 'active' => 'injongepay.overview', 'permission' => 'list injongepay.overview', 'order' => 10, 'icon' => $icon],
            ['label' => 'injongepay::injongepay.menu_payments', 'route' => 'injongepay.payments.index', 'active' => 'injongepay.payments.*', 'permission' => 'list injongepay.payment', 'order' => 20, 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['label' => 'injongepay::injongepay.menu_transfers', 'route' => 'injongepay.transfers.index', 'active' => 'injongepay.transfers.*', 'permission' => 'list injongepay.transfer', 'order' => 30, 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
            ['label' => 'injongepay::injongepay.menu_cashouts', 'route' => 'injongepay.cashouts.index', 'active' => 'injongepay.cashouts.*', 'permission' => 'list injongepay.cashout', 'order' => 40, 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'injongepay::injongepay.menu_platform_cashouts', 'route' => 'injongepay.platform.cashouts.index', 'active' => 'injongepay.platform.cashouts.*', 'permission' => 'list injongepay_platform.cashout', 'group' => 'Injonge Pay platform', 'order' => 22, 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'injongepay::injongepay.menu_reports', 'route' => 'injongepay.reports', 'active' => 'injongepay.reports*', 'permission' => 'list injongepay.report', 'order' => 60, 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['label' => 'injongepay::injongepay.menu_finance', 'route' => 'injongepay.platform.finance', 'active' => 'injongepay.platform.finance*', 'permission' => 'list injongepay_platform.reconciliation', 'group' => 'Injonge Pay platform', 'order' => 5, 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['label' => 'injongepay::injongepay.menu_my_settlement_accounts', 'route' => 'injongepay.settlement-accounts.index', 'active' => 'injongepay.settlement-accounts.*', 'permission' => 'list injongepay.settlement_account', 'order' => 45, 'icon' => 'M3 6l9-4 9 4M4 10h16M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18'],
            ['label' => 'injongepay::injongepay.menu_approvals', 'route' => 'injongepay.approvals.index', 'active' => 'injongepay.approvals.*', 'permission' => 'approve injongepay.transfer|approve injongepay.cashout', 'order' => 50, 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
            ['label' => 'injongepay::injongepay.menu_profile', 'route' => 'injongepay.profile', 'active' => 'injongepay.profile*', 'permission' => 'read injongepay.profile', 'order' => 85, 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['label' => 'injongepay::injongepay.menu_security', 'route' => 'injongepay.security', 'active' => 'injongepay.security*', 'permission' => 'create injongepay.transfer|create injongepay.cashout|approve injongepay.transfer|approve injongepay.cashout', 'order' => 90, 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            ['label' => 'injongepay::injongepay.menu_reconciliation', 'route' => 'injongepay.platform.reconciliation.index', 'active' => 'injongepay.platform.reconciliation.*', 'permission' => 'list injongepay_platform.reconciliation', 'group' => 'Injonge Pay platform', 'order' => 25, 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['label' => 'injongepay::injongepay.menu_unallocated', 'route' => 'injongepay.platform.unallocated.index', 'active' => 'injongepay.platform.unallocated.*', 'permission' => 'list injongepay_platform.reconciliation', 'group' => 'Injonge Pay platform', 'order' => 30, 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'injongepay::injongepay.menu_businesses', 'route' => 'injongepay.platform.businesses.index', 'active' => 'injongepay.platform.businesses.*', 'permission' => 'list injongepay_platform.business', 'group' => 'Injonge Pay platform', 'order' => 10, 'icon' => $building],
            ['label' => 'injongepay::injongepay.menu_wallets', 'route' => 'injongepay.platform.wallets.index', 'active' => 'injongepay.platform.wallets.*', 'permission' => 'list injongepay_platform.wallet', 'group' => 'Injonge Pay platform', 'order' => 20, 'icon' => $icon],
            ['label' => 'injongepay::injongepay.menu_bank_calls', 'route' => 'injongepay.platform.bank-calls.index', 'active' => 'injongepay.platform.bank-calls.*', 'permission' => 'list injongepay_platform.bank', 'group' => 'Injonge Pay platform', 'order' => 80, 'icon' => 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['label' => 'injongepay::injongepay.menu_platform_settings', 'route' => 'injongepay.platform.settings', 'active' => 'injongepay.platform.settings*', 'permission' => 'update injongepay_platform.setting|update injongepay_platform.fee', 'group' => 'Injonge Pay platform', 'order' => 90, 'icon' => $gear],
        ];
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list injongepay.overview')) {
            return [];
        }

        $wallets = Wallet::query()->whereNull('internal_role')->get(['pending_balance', 'available_balance']);

        if ($wallets->isEmpty()) {
            return [];
        }

        return [[
            'label' => __('injongepay::injongepay.dashboard_balance'),
            'value' => Money::format((int) $wallets->sum(fn (Wallet $w) => $w->balance())),
            'href' => route('injongepay.overview'),
        ]];
    }
}
