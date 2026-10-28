<?php

namespace Modules\Reports\Http\Controllers;

use Modules\Reports\Services\ReportService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function modifyAdminMenu(): array
    {
        // Each report checks its own module and permission; show the menu when any is available.
        if (app(ReportService::class)->available() === []) {
            return [];
        }

        return [[
            'label' => 'reports::reports.reports',
            'route' => 'reports.index',
            'active' => 'reports.*',
            'order' => 10,
            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ]];
    }
}
