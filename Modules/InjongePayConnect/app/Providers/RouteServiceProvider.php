<?php

namespace Modules\InjongePayConnect\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Modules\InjongePayConnect\Http\Middleware\AuthenticatePartner;
use Modules\InjongePayConnect\Http\Middleware\LogPartnerCall;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'InjongePayConnect';

    public function boot(): void
    {
        parent::boot();

        Route::aliasMiddleware('partner.auth', AuthenticatePartner::class);

        // Per connection (not per IP: many tills share one address).
        RateLimiter::for('injongepay-partner', function (Request $request) {
            $connection = $request->attributes->get('partner_connection');

            return Limit::perMinute($connection?->rate_limit ?: (int) config('injongepayconnect.rate_limit', 120))->by('conn:'.($connection?->id ?? $request->ip()));
        });
        RateLimiter::for('injongepay-partner-token', fn (Request $request) => Limit::perMinute(30)->by('token:'.$request->input('client_id').'|'.$request->ip()));
    }

    public function map(): void
    {
        Route::middleware(['web', 'module:InjongePayConnect'])->group(module_path($this->name, '/routes/web.php'));
        Route::middleware(['api', 'module:InjongePayConnect', LogPartnerCall::class])->group(module_path($this->name, '/routes/api.php'));
    }
}
