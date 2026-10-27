<?php

namespace Modules\Billing\Http\Controllers;

use App\Models\Business\Business;
use Modules\Billing\Models\Package;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingEntitlements;
use Modules\Billing\Support\BillingSettings;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'billing' => ['subscription', 'invoice', 'payment'],
            ],
            'module_labels' => ['billing' => 'Billing'],
            'controller_labels' => [
                'subscription' => 'Subscription & plan',
                'invoice' => 'Invoices',
                'payment' => 'Payments',
            ],
        ];
    }

    /**
     * Business sidebar ("Billing" section) + a Platform link for superadmins.
     */
    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'billing::billing.my_subscription',
                'route' => 'billing.index',
                'active' => 'billing.*',
                'permission' => 'list billing.subscription',
                'order' => 10,
                'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            ],
            [
                'label' => 'billing::billing.title',
                'route' => 'superadmin.billing.overview',
                'active' => 'superadmin.billing.*',
                'ability' => 'superadmin',
                'group' => 'Platform',
                'order' => 25,
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
        ];
    }

    public function superadmin_menu(): array
    {
        return [
            ['label' => __('billing::billing.title'), 'route' => 'superadmin.billing.overview', 'active' => 'superadmin.billing.*'],
        ];
    }

    /**
     * Subscription card on the superadmin business page.
     */
    public function superadmin_business_panel(Business $business): string
    {
        $entitlements = app(BillingEntitlements::class);

        return view('billing::admin.partials.business-panel', [
            'business' => $business,
            'subscription' => app(SubscriptionService::class)->current($business),
            'isPlatformOwner' => $entitlements->isPlatformOwner($business),
            'packages' => Package::query()->where('is_active', true)->ordered()->get(),
        ])->render();
    }

    /**
     * New businesses start on the default package, if one is set.
     */
    public function after_business_created(array $args): void
    {
        $packageId = BillingSettings::get('default_package_id');
        $package = $packageId ? Package::query()->where('is_active', true)->find($packageId) : null;

        if ($package && ! app(BillingEntitlements::class)->isPlatformOwner($args['business'])) {
            app(SubscriptionService::class)->subscribe($args['business'], $package, null, true);
        }
    }
}
