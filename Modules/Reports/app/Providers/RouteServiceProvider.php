<?php

namespace Modules\Reports\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Reports';

    public function map(): void
    {
        Route::middleware(['web', 'module:Reports'])->group(module_path($this->name, '/routes/web.php'));
    }
}
