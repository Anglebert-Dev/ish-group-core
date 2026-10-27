<?php

namespace Modules\Frontend\Http\Controllers;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 * See docs/modules-roles-superadmin-reference.txt §1b and the README.
 */
class DataController
{
    /**
     * Claim "/" for the public website while this module is active.
     */
    public function landing_page(): array
    {
        return [PublicHomeController::class, 'index'];
    }
}
