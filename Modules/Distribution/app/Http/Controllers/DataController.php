<?php

namespace Modules\Distribution\Http\Controllers;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 */
class DataController
{
    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'distribution::distribution.title',
                'route' => 'superadmin.distribution.sites.index',
                'active' => 'superadmin.distribution.*',
                'ability' => 'superadmin',
                'group' => 'Platform',
                'order' => 26,
                'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
            ],
        ];
    }

    public function superadmin_menu(): array
    {
        return [
            ['label' => __('distribution::distribution.title'), 'route' => 'superadmin.distribution.sites.index', 'active' => 'superadmin.distribution.*'],
        ];
    }
}
