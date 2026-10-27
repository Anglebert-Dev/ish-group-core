<?php

namespace Modules\Production\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Production';

    public function map(): void
    {
        Route::middleware(['web', 'module:Production'])->group(module_path($this->name, '/routes/web.php'));
    }
}
