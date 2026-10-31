<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Install\RequirementsChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\SiteService;
use Modules\Distribution\Support\PlatformAudit;
use Modules\Distribution\Support\ServerDetails;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer's server details (typed in, or their filled-in CSV) and the
 * setup guide we send them.
 */
class ServerDetailsController extends Controller
{
    public function __construct(protected SiteService $sites)
    {
    }

    public function edit(Site $site): View
    {
        return view('distribution::sites.server', [
            'site' => $site,
            'fields' => ServerDetails::fields(),
            'details' => (array) $site->server_details,
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        [$details, $errors] = ServerDetails::clean($request->only(array_keys(ServerDetails::fields())));

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $result = $this->sites->saveServerDetails($site, $details, 'form');

        return redirect()->route('superadmin.distribution.sites.show', $site)->with('success', $result['message']);
    }

    public function upload(Request $request, Site $site): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:512', 'mimes:csv,txt']]);

        [$details, $errors, $unknown] = ServerDetails::fromCsv($request->file('file')->getRealPath());

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        $result = $this->sites->saveServerDetails($site, $details, 'csv');
        $message = $result['message'].($unknown ? ' '.__('distribution::distribution.csv_unknown_fields', ['fields' => implode(', ', $unknown)]) : '');

        return redirect()->route('superadmin.distribution.sites.show', $site)->with('success', $message);
    }

    public function template(Site $site): Response
    {
        $name = 'server-details-'.(Str::slug($site->name) ?: 'site').'.csv';

        return response(ServerDetails::csvTemplate((array) $site->server_details), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * Printable page for the customer (Print → Save as PDF).
     */
    public function guide(Site $site): View
    {
        PlatformAudit::log('distribution_guide_opened', "Setup guide opened for site {$site->name}", $site);

        $details = (array) $site->server_details;

        return view('distribution::guide', [
            'site' => $site,
            'details' => $details,
            'fields' => ServerDetails::fields(),
            'extensions' => RequirementsChecker::EXTENSIONS,
            'minPhp' => RequirementsChecker::MIN_PHP,
            'cronLine' => ServerDetails::cronLine($details),
            'problems' => ServerDetails::problems($details),
            'provider' => config('app.name'),
        ]);
    }
}
