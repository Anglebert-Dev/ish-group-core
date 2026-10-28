<?php

namespace Modules\GarmentPack\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'GarmentPack';

    public function map(): void
    {
        Route::middleware(['web', 'module:GarmentPack'])->group(module_path($this->name, '/routes/web.php'));
    }
}
