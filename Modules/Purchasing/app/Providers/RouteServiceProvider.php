<?php

namespace Modules\Purchasing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Purchasing';

    public function map(): void
    {
        Route::middleware(['web', 'module:Purchasing'])->group(module_path($this->name, '/routes/web.php'));
    }
}
