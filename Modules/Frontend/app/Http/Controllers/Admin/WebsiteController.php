<?php

namespace Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Frontend\Http\Requests\UpdateWebsiteRequest;
use Modules\Frontend\Services\SiteService;

/**
 * Staff editor for the signed-in user's business website.
 */
class WebsiteController extends Controller
{
    public const TABS = ['general', 'home', 'pages', 'layout'];

    public function __construct(protected SiteService $sites)
    {
    }

    public function edit(string $tab = 'general'): View
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        return view('frontend::admin.website.edit', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'site' => $this->sites->forBusiness(Auth::guard('staff')->user()->business),
        ]);
    }

    public function update(UpdateWebsiteRequest $request, string $tab): RedirectResponse
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        $site = $this->sites->forBusiness(Auth::guard('staff')->user()->business);
        $result = $this->sites->update($site, $tab, $request->validated());

        return redirect()->route('frontend.admin.website.edit', $tab)->with('success', $result['message']);
    }

    public function view(): RedirectResponse
    {
        $site = $this->sites->forBusiness(Auth::guard('staff')->user()->business);

        return redirect()->route('site.home', ['site' => $site->slug]);
    }
}
