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
                'injongepay' => ['overview', 'wallet', 'payment', 'ledger', 'transfer', 'cashout', 'settlement_account', 'report'],
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
                'settlement_account' => 'Bank accounts for cash-outs',
                'report' => 'Reports',
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
