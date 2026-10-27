<?php

namespace Modules\SalesOrders\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'SalesOrders';

    public function map(): void
    {
        Route::middleware(['web', 'module:SalesOrders'])->group(module_path($this->name, '/routes/web.php'));
    }
}
