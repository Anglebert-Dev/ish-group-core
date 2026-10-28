<?php

namespace Modules\Delivery\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Delivery';

    public function map(): void
    {
        Route::middleware(['web', 'module:Delivery'])->group(module_path($this->name, '/routes/web.php'));
    }
}
