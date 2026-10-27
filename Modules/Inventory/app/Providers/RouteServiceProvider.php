<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Inventory';

    public function map(): void
    {
        Route::middleware(['web', 'module:Inventory'])->group(module_path($this->name, '/routes/web.php'));
    }
}
