<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Frontend\Services\SiteService;
use Modules\Frontend\Support\SiteContext;

/**
 * Public pages of a business site. The site itself is resolved by the
 * ResolveSite middleware (/s/{site}) or primary() for "/".
 */
class SiteController extends Controller
{
    public const PAGES = ['about', 'services', 'contact', 'privacy', 'terms'];

    public function home(): View
    {
        return view('frontend::pages.home');
    }

    public function page(string $page): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view('frontend::pages.'.$page);
    }

    /**
     * "/" while this module provides the landing page: the primary
     * business's published site (see DataController::landing_page).
     */
    public function primary(SiteService $sites, SiteContext $context): View
    {
        $site = $sites->primary();
        abort_unless($site, 404);

        $context->apply($site);

        return $this->home();
    }
}
