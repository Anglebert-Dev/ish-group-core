<?php

namespace Modules\Distribution\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Distribution\Http\Middleware\AuthenticateSite;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Distribution';

    public function map(): void
    {
        Route::middleware(['web', 'module:Distribution'])->group(module_path($this->name, '/routes/web.php'));

        Route::middleware(['api', 'module:Distribution', AuthenticateSite::class, 'throttle:distribution-api'])
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
