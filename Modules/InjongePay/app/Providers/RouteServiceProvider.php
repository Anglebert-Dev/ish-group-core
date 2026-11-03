<?php

namespace Modules\InjongePay\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'InjongePay';

    public function map(): void
    {
        Route::middleware(['web', 'module:InjongePay'])->group(module_path($this->name, '/routes/web.php'));
    }
}
