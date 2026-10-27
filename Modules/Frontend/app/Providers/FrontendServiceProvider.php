<?php

namespace Modules\Frontend\Providers;

use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Routing\Router;
use Modules\Frontend\Http\Middleware\EnsureCustomerIsActive;
use Modules\Frontend\Models\Customer;

/**
 * Public website + customer accounts. Everything customer-related lives in
 * this module, so with Frontend off the core has no customer guard at all.
 */
class FrontendServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Frontend';

    protected string $nameLower = 'frontend';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->registerCustomerGuard();
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)->aliasMiddleware('customer.active', EnsureCustomerIsActive::class);
    }

    private function registerCustomerGuard(): void
    {
        $config = $this->app['config'];

        $config->set('auth.guards.customer', [
            'driver' => 'session',
            'provider' => 'customers',
        ]);

        $config->set('auth.providers.customers', [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ]);

        $config->set('auth.passwords.customers', [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ]);
    }
}
