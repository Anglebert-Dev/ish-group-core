<?php

namespace App\Http\Middleware;

use App\Contracts\Entitlements;
use App\Services\Module\ModuleManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: ->middleware('module:Inventory')
 *
 * 404 unless the module is installed and enabled, and — for a signed-in
 * staff user — switched on for their business (enabled_modules). A module
 * outside the business's package redirects to the upgrade page.
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

        if ($business && ! app(Entitlements::class)->allowsModule($business, $module)) {
            // Not in the business's package (or the subscription lapsed):
            // send them where they can fix it, if such a page exists.
            $upgrade = app(Entitlements::class)->upgradeUrl();
            abort_unless($upgrade, 404);

            return redirect()->to($upgrade)->with('error', __('body.module_not_in_package', ['module' => $module]));
        }

        return $next($request);
    }
}
