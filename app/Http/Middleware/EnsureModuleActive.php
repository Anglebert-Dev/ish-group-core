<?php

namespace App\Http\Middleware;

use App\Contracts\Entitlements;
use App\Services\Module\ModuleManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: ->middleware('module:Inventory')
 *
 * 404 unless the module is installed and enabled and the signed-in staff
 * user's business may use it (its package, or its on/off switch when no
 * billing module is installed). Locked modules redirect to the upgrade page.
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

        if ($business && ! $business->canUseModule($module)) {
            // Not in the business's package (or the subscription lapsed):
            // send them where they can fix it, if such a page exists.
            $upgrade = app(Entitlements::class)->upgradeUrl();
            abort_unless($upgrade, 404);

            return redirect()->to($upgrade)->with('error', __('body.module_not_in_package', ['module' => $module]));
        }

        return $next($request);
    }
}
