<?php

namespace Modules\Quotations\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Quotations';

    public function map(): void
    {
        Route::middleware(['web', 'module:Quotations'])->group(module_path($this->name, '/routes/web.php'));
    }
}
