<?php

namespace Modules\Billing\Support;

use App\Contracts\Entitlements;
use App\Models\Business\Business;
use App\Models\User;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionService;

/**
 * Packages decide what a business may use:
 *  - Billing not installed/enabled  → everything allowed
 *  - modules marked billing_exempt  → always allowed (e.g. Billing itself)
 *  - the superadmin's own business  → everything, no limits
 *  - otherwise                      → modules and limits of the current
 *    subscription, while it is trialing, active or in its grace period
 */
class BillingEntitlements implements Entitlements
{
    /** @var array<int, Subscription|false> */
    private array $subscriptions = [];

    /** @var array<int, bool> */
    private array $platformOwners = [];

    public function __construct(protected ModuleManager $modules)
    {
    }

    public function controlsModules(): bool
    {
        return $this->billingActive();
    }

    public function allowsModule(Business $business, string $module): bool
    {
        if (! $this->billingActive() || $this->modules->isBillingExempt($module) || $this->isPlatformOwner($business)) {
            return true;
        }

        $subscription = $this->subscription($business);

        return $subscription && $subscription->status->grantsAccess() && $subscription->includesModule($module);
    }

    public function limit(Business $business, string $key): ?int
    {
        if (! $this->billingActive() || $this->isPlatformOwner($business)) {
            return null;
        }

        $subscription = $this->subscription($business);

        if ($subscription && $subscription->status->grantsAccess()) {
            return $subscription->limit($key);
        }

        // No usable subscription: the platform's "without a package" limits.
        if ($key === 'users') {
            $free = BillingSettings::int('free_users_limit');

            return $free > 0 ? $free : null;
        }

        return null;
    }

    public function upgradeUrl(): ?string
    {
        return $this->billingActive() && Route::has('billing.index') ? route('billing.index') : null;
    }

    public function subscription(Business $business): ?Subscription
    {
        if (! array_key_exists($business->id, $this->subscriptions)) {
            $this->subscriptions[$business->id] = app(SubscriptionService::class)->current($business) ?? false;
        }

        return $this->subscriptions[$business->id] ?: null;
    }

    /**
     * Drop memoised subscriptions (called whenever one is saved).
     */
    public function forget(): void
    {
        $this->subscriptions = [];
    }

    public function isPlatformOwner(Business $business): bool
    {
        return $this->platformOwners[$business->id] ??= User::query()
            ->where('business_id', $business->id)
            ->where('is_superadmin', true)
            ->exists();
    }

    private function billingActive(): bool
    {
        return $this->modules->isActive('Billing');
    }
}
