<?php

namespace Modules\Frontend\Providers;

use App\Models\Customer\Customer;
use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Session\Middleware\StartSession;
use Modules\Frontend\Http\Middleware\EnsureCustomerIsActive;
use Modules\Frontend\Http\Middleware\EnsureCustomerAccounts;
use Modules\Frontend\Http\Middleware\ResolveSite;
use Modules\Frontend\Models\Site;
use Modules\Frontend\Support\SiteContext;

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

        $this->app->scoped(SiteContext::class);
        $this->registerCustomerGuard();
    }

    public function boot(): void
    {
        parent::boot();

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('customer.active', EnsureCustomerIsActive::class);
        $router->aliasMiddleware('frontend.site', ResolveSite::class);
        $router->aliasMiddleware('frontend.accounts', EnsureCustomerAccounts::class);

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(3)->by('contact|'.$request->ip()));

        // Resolve the site after the session starts (it needs to know who is
        // signed in) but before auth:customer runs, so a customer of another
        // business's site is already a guest by then.
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->addToMiddlewarePriorityAfter(StartSession::class, ResolveSite::class);
        // Account pages 404 (not "please log in") while customer login is off.
        $kernel->addToMiddlewarePriorityAfter(ResolveSite::class, EnsureCustomerAccounts::class);
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

        // Reset links point at the customer's business website.
        Customer::$resetUrlResolver = fn (Customer $customer, string $token): string => url(route('customer.password.reset', [
            'site' => Site::query()->withoutGlobalScope('business')->where('business_id', $customer->business_id)->value('slug'),
            'token' => $token,
            'email' => $customer->getEmailForPasswordReset(),
        ], false));
    }
}
