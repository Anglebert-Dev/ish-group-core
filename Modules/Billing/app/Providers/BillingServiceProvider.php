<?php

namespace Modules\Billing\Providers;

use App\Contracts\Entitlements;
use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Billing\Console\RunBillingCommand;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\BillingEntitlements;
use Modules\Billing\Support\BillingSettings;

/**
 * Packages, subscriptions, invoices and offline payments. Replaces the
 * core's "everything allowed" Entitlements with package-based rules.
 */
class BillingServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Billing';

    protected string $nameLower = 'billing';

    protected array $commands = [
        RunBillingCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->scoped(BillingEntitlements::class);
        $this->app->scoped(Entitlements::class, fn ($app) => $app->make(BillingEntitlements::class));
        $this->app->scoped('billing.settings', fn () => BillingSettings::load());
    }

    public function boot(): void
    {
        parent::boot();

        // A subscription change must be visible to the rest of the request.
        Subscription::saved(function () {
            if ($this->app->resolved(BillingEntitlements::class)) {
                $this->app->make(BillingEntitlements::class)->forget();
            }
        });
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('billing:run')->dailyAt('01:00')->withoutOverlapping();
    }
}
