<?php

namespace Modules\Frontend\Http\Controllers;

use App\Models\Customer\Customer;
use App\Support\Tenancy\CurrentBusiness;
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

    /**
     * Customers sign in on the business website, when the business turned
     * customer login on. Called without a customer to ask whether it is.
     */
    public function customer_portal_url(?Customer $customer = null): ?string
    {
        $business = app(CurrentBusiness::class)->get();

        $site = $business ? $this->sites->forBusiness($business) : null;

        return $site?->allowsLogin() ? route('login', ['site' => $site->slug]) : null;
    }

    public function user_permissions(): array
    {
        return [
            'access_modules' => [
                'website' => ['site', 'message'],
            ],
            'module_labels' => ['website' => 'Website'],
            'controller_labels' => ['site' => 'Website content', 'message' => 'Website messages'],
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
                'label' => 'body.website_messages',
                'route' => 'frontend.admin.messages.index',
                'active' => 'frontend.admin.messages.*',
                'permission' => 'list website.message',
                'order' => 15,
                'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
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
