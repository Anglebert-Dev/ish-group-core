<?php

namespace App\Http\Middleware;

use App\Services\Module\ModuleManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: ->middleware('module:Inventory')
 *
 * 404 unless the module is installed and enabled, and — for a signed-in
 * staff user — switched on for their business (enabled_modules).
 */
class EnsureModuleActive
{
    public function __construct(protected ModuleManager $modules)
    {
    }

    public function handle(Request $request, Closure $next, string $module)
    {
        abort_unless($this->modules->isActive($module), 404);

        $business = auth('staff')->user()?->business;

        abort_if($business && ! $business->hasModuleEnabled($module), 404);

        return $next($request);
    }
}
