<?php

namespace Modules\InjongePay\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'InjongePay';

    public function boot(): void
    {
        parent::boot();

        // GTBank can send bursts; limited per sending address, generously.
        RateLimiter::for('injongepay-webhook', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }

    public function map(): void
    {
        Route::middleware(['web', 'module:InjongePay'])->group(module_path($this->name, '/routes/web.php'));

        // GTBank webhooks: same URLs as before (/api/payments/webhook, /api/gtbank/disbursement-status).
        Route::middleware(['api', 'module:InjongePay', 'throttle:injongepay-webhook'])->prefix('api')->group(module_path($this->name, '/routes/api.php'));
    }
}
