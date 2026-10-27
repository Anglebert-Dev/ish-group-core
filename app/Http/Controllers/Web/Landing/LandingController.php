<?php

namespace App\Http\Controllers\Web\Landing;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleManager;

/**
 * "/" — goes straight into the system unless an active module (such as
 * Frontend) offers a landing page through its landing_page() hook, which
 * returns a controller callable, e.g. [PublicHomeController::class, 'index'].
 */
class LandingController extends Controller
{
    public function __invoke(ModuleManager $modules)
    {
        foreach ($modules->cachedHook('landing_page') as $callable) {
            if (is_array($callable) && is_string($callable[0] ?? null)) {
                $callable[0] = app($callable[0]);
            }

            if (is_callable($callable)) {
                return app()->call($callable);
            }
        }

        return auth('staff')->check()
            ? redirect()->route('dashboard')
            : redirect()->route('staff.login');
    }
}
