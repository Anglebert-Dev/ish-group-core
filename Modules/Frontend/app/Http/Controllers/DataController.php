<?php

namespace Modules\Frontend\Http\Controllers;

use Modules\Frontend\Services\SiteService;

/**
 * Hook implementations the core calls through ModuleManager::hook().
 * See the README ("Building a module").
 */
class DataController
{
    public function __construct(protected SiteService $sites)
    {
    }

    /**
     * Claim "/" for the primary business's website, once it is published.
     */
    public function landing_page(): ?array
    {
        return $this->sites->primary() ? [SiteController::class, 'primary'] : null;
    }

    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'website' => ['site', 'customer'],
            ],
            'module_labels' => ['website' => 'Website'],
            'controller_labels' => ['site' => 'Website content', 'customer' => 'Website customers'],
        ];
    }

    /**
     * The "Website" section of the staff sidebar.
     */
    public function modifyAdminMenu(): array
    {
        return [
            [
                'label' => 'body.website_edit',
                'route' => 'frontend.admin.website.edit',
                'active' => 'frontend.admin.website.edit',
                'permission' => 'read website.site',
                'order' => 10,
                'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            ],
            [
                'label' => 'body.website_customers',
                'route' => 'frontend.admin.customers.index',
                'active' => 'frontend.admin.customers.*',
                'permission' => 'list website.customer',
                'order' => 20,
                'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z',
            ],
            [
                'label' => 'body.website_view',
                'route' => 'frontend.admin.website.view',
                'active' => 'frontend.admin.website.view',
                'permission' => 'read website.site',
                'order' => 30,
                'icon' => 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
            ],
        ];
    }
}
