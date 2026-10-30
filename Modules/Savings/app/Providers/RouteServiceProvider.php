<?php

namespace Modules\Savings\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Savings';

    public function map(): void
    {
        Route::middleware(['web', 'module:Savings'])->group(module_path($this->name, '/routes/web.php'));
    }
}
