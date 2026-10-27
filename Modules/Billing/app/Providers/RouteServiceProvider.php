<?php

namespace Modules\Billing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Billing';

    public function map(): void
    {
        Route::middleware(['web', 'module:Billing'])->group(module_path($this->name, '/routes/web.php'));
    }
}
