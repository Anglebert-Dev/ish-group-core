<?php

namespace Modules\Frontend\Support;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Modules\Frontend\Models\Site;

/**
 * Makes one business's site the context of the current request: its
 * branding, its customers, and URLs that carry its {site} slug.
 */
class SiteContext
{
    private ?Site $site = null;

    public function apply(Site $site): void
    {
        $this->site = $site;

        app(CurrentBusiness::class)->set($site->business);
        URL::defaults(['site' => $site->slug]);
        View::share('site', $site);

        // A customer signed in on another business's site is a guest here.
        $customer = Auth::guard('customer')->user();
        if ($customer && (int) $customer->business_id !== (int) $site->business_id) {
            Auth::guard('customer')->logout();
        }
    }

    public function site(): ?Site
    {
        return $this->site;
    }
}
