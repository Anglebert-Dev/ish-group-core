<?php

namespace Modules\InjongePayConnect\Http\Controllers;

class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'injongepay_connect' => ['connection', 'invoice'],
                'injongepay_connect_platform' => ['integrator', 'call'],
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

        $items = [
            ['label' => 'injongepayconnect::connect.menu_business', 'route' => 'injongepayconnect.connections.index', 'active' => 'injongepayconnect.connections*|injongepayconnect.invoices', 'permission' => 'list injongepay_connect.connection|list injongepay_connect.invoice', 'order' => 70, 'icon' => $plug],
            ['label' => 'injongepayconnect::connect.menu_platform', 'route' => 'injongepayconnect.platform.integrators.index', 'active' => 'injongepayconnect.platform.*', 'permission' => 'list injongepay_connect_platform.integrator', 'group' => 'Injonge Pay platform', 'order' => 70, 'icon' => $plug],
        ];

        // Inhills (the operator) has no merchant codes of its own: only its platform menu.
        if (app(\App\Support\Access\PlatformAccess::class)->isOperator(auth('staff')->user())) {
            $items = array_values(array_filter($items, fn (array $item) => isset($item['group'])));
        }

        return $items;
    }
}
