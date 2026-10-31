<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Distribution\Enums\SiteStatus;
use Modules\Distribution\Enums\UpdateMode;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\SiteToken;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\SiteService;
use Modules\Distribution\Support\PlatformAudit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteController extends Controller
{
    public function __construct(protected SiteService $sites)
    {
    }

    public function index(): View
    {
        return view('distribution::sites.index', [
            'sites' => Site::query()->withCount('modules')->latest('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('distribution::sites.form', [
            'site' => new Site(['update_mode' => UpdateMode::Manual, 'status' => SiteStatus::Active]),
            'modules' => $this->sites->assignableModules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['modules' => ['array'], 'modules.*' => ['string']]);
        $result = $this->sites->create(collect($data)->except('modules')->all(), $data['modules'] ?? []);

        if (! $result['status']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('superadmin.distribution.sites.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Site $site): View
    {
        $site->load('modules', 'tokens', 'builds');
        $site->setRelation('updateReports', $site->updateReports()->limit(20)->get());
        $site->setRelation('checkIns', $site->checkIns()->limit(10)->get());

        return view('distribution::sites.show', [
            'site' => $site,
            'latest' => Release::latestFor($site->moduleNames()),
            'assignable' => array_values(array_diff($this->sites->assignableModules(), $site->moduleNames())),
        ]);
    }

    public function edit(Site $site): View
    {
        return view('distribution::sites.form', ['site' => $site, 'modules' => []]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $result = $this->sites->update($site, $request->validate($this->rules()));

        return redirect()->route('superadmin.distribution.sites.show', $site)->with('success', $result['message']);
    }

    public function assign(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate(['modules' => ['required', 'array', 'min:1'], 'modules.*' => ['string']]);
        $result = $this->sites->assign($site, $data['modules']);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function unassign(Site $site, string $module): RedirectResponse
    {
        $result = $this->sites->unassign($site, $module);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Builds right away (about 10 seconds): no queue worker needed.
     */
    public function build(Site $site, BuildService $builds): RedirectResponse
    {
        $started = $builds->start($site);

        if (! $started['status']) {
            return back()->with('error', $started['message']);
        }

        @set_time_limit(0);
        $result = $builds->run($started['data']['build']);

        if (! $result['status']) {
            return back()->with('error', $result['message']);
        }

        // Shown once: only its hash is kept (and put in the zip).
        return back()->with('success', $result['message'])->with('install_code', $started['data']['code']);
    }

    public function deleteBuild(Site $site, Build $build, BuildService $builds): RedirectResponse
    {
        abort_unless($build->site_id === $site->id, 404);

        $result = $builds->delete($build);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function download(Site $site, Build $build, BuildService $builds): BinaryFileResponse
    {
        abort_unless($build->site_id === $site->id && $build->isDownloadable(), 404);

        PlatformAudit::log('distribution_build_downloaded', "Zip downloaded for site {$site->name}", $build, ['file' => $build->filename]);

        return response()->download($builds->absolutePath($build), $build->filename);
    }

    public function revokeToken(Site $site, SiteToken $token): RedirectResponse
    {
        abort_unless($token->site_id === $site->id, 404);

        $result = $this->sites->revokeToken($token);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'app_url' => ['nullable', 'url:http,https', 'max:255'],
            'update_mode' => ['required', Rule::enum(UpdateMode::class)],
            'status' => ['required', Rule::enum(SiteStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
