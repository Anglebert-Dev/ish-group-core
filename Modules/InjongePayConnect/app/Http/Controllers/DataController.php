<?php

namespace Modules\InjongePayConnect\Http\Controllers;

class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'injongepay_connect' => ['connection', 'invoice'],
                'injongepay_connect_platform' => ['integrator', 'connection', 'call'],
            ],
            'platform_modules' => ['injongepay_connect_platform'],
            'module_labels' => [
                'injongepay_connect' => 'Injonge Pay integrations',
                'injongepay_connect_platform' => 'Injonge Pay integrations (operator only)',
            ],
            'controller_labels' => [
                'connection' => 'Connected systems (POS, EBM…)',
                'invoice' => 'Invoices linked to payments',
                'integrator' => 'Integrators and their contracts',
                'call' => 'Partner API call log',
            ],
        ];
    }

    public function modifyAdminMenu(): array
    {
        $plug = 'M13 10V3L4 14h7v7l9-11h-7z';

        return [
            ['label' => 'injongepayconnect::connect.menu_business', 'route' => 'injongepayconnect.connections', 'active' => 'injongepayconnect.connections*', 'permission' => 'list injongepay_connect.connection|list injongepay_connect.invoice', 'order' => 70, 'icon' => $plug],
            ['label' => 'injongepayconnect::connect.menu_platform', 'route' => 'injongepayconnect.platform.index', 'active' => 'injongepayconnect.platform.*', 'permission' => 'list injongepay_connect_platform.connection', 'group' => 'Injonge Pay platform', 'order' => 70, 'icon' => $plug],
        ];
    }
}
