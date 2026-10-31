<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\CustomerMailer;
use Modules\Distribution\Support\PlatformAudit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Emailing a customer (superadmin) and the signed links in those emails
 * (the customer, no account needed).
 */
class CustomerController extends Controller
{
    public function emailPreparation(Request $request, Site $site, CustomerMailer $mailer): RedirectResponse
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];
        $result = $mailer->sendPreparation($site, $email);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function emailPackage(Request $request, Site $site, Build $build, CustomerMailer $mailer): RedirectResponse
    {
        abort_unless($build->site_id === $site->id, 404);

        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];
        $result = $mailer->sendPackage($build, $email);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /** Signed link from the emails. */
    public function guide(Site $site): View
    {
        return ServerDetailsController::guideView($site);
    }

    /** Signed link from "Your package is ready"; works until the zip expires. */
    public function download(Build $build, BuildService $builds): BinaryFileResponse
    {
        abort_unless($build->isDownloadable(), 404);

        PlatformAudit::log('distribution_build_downloaded_by_customer', "Zip downloaded by the customer of site {$build->site->name}", $build, ['file' => $build->filename]);

        return response()->download($builds->absolutePath($build), $build->filename);
    }
}
