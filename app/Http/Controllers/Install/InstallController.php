<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Install\DatabaseProbe;
use App\Services\Install\InstallationService;
use App\Services\Install\InstallRules;
use App\Services\Install\MailProbe;
use App\Services\Install\RequirementsChecker;
use App\Support\Installer\SiteManifest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web installer (docs/self-hosting-build-guide.md §5.1). Answers are kept
 * in the (encrypted, file) session until the last step runs everything
 * through InstallationService — the same routine as core:setup.
 */
class InstallController extends Controller
{
    /** Wizard order; each page needs the ones before it done. */
    public const STEPS = ['code', 'requirements', 'database', 'app', 'mail', 'admin', 'review'];

    public function start(): RedirectResponse
    {
        return redirect()->route('install.'.$this->firstOpenStep());
    }

    public function code(SiteManifest $manifest): View|RedirectResponse
    {
        if (! $manifest->requiresCode()) {
            $this->complete('code');

            return redirect()->route('install.requirements');
        }

        return $this->page('code');
    }

    public function checkCode(Request $request, SiteManifest $manifest): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        if (! $manifest->checkCode((string) $request->input('code'))) {
            return redirect()->route('install.code')->withErrors(['code' => __('install.code_wrong')]);
        }

        $this->complete('code');

        return redirect()->route('install.requirements');
    }

    public function requirements(RequirementsChecker $checker): View|RedirectResponse
    {
        return $this->guard('requirements') ?? $this->page('requirements', [
            'checks' => $checker->check(),
            'passes' => $checker->passes(),
        ]);
    }

    public function acceptRequirements(RequirementsChecker $checker): RedirectResponse
    {
        if ($redirect = $this->guard('requirements')) {
            return $redirect;
        }

        if (! $checker->passes()) {
            return redirect()->route('install.requirements')->withErrors(['requirements' => __('install.req_fix_first')]);
        }

        $this->complete('requirements');

        return redirect()->route('install.database');
    }

    public function database(RequirementsChecker $checker, SiteManifest $manifest): View|RedirectResponse
    {
        return $this->guard('database') ?? $this->page('database', [
            'drivers' => $checker->databaseDrivers(),
            'values' => session('install.data.database') ?? $manifest->defaults('database'),
        ]);
    }

    public function saveDatabase(Request $request, DatabaseProbe $probe, InstallationService $installer): RedirectResponse
    {
        if ($redirect = $this->guard('database')) {
            return $redirect;
        }

        $db = $request->validate(InstallRules::database());
        $result = $probe->test($db);

        if (! $result['ok'] && ! ($result['has_users'] && $installer->resuming())) {
            return redirect()->route('install.database')->withInput($request->except('password'))->withErrors(['database' => $result['message']]);
        }

        $this->remember('database', $db);
        $this->complete('database');

        return redirect()->route('install.app')->with('status', $result['message']);
    }

    public function app(Request $request, SiteManifest $manifest): View|RedirectResponse
    {
        return $this->guard('app') ?? $this->page('app', [
            'values' => session('install.data.app') ?? $manifest->defaults('app') + [
                'name' => config('app.name'),
                'url' => $request->getSchemeAndHttpHost().rtrim($request->getBasePath(), '/'),
                'timezone' => config('app.timezone'),
                'queue_via_scheduler' => true,
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function saveApp(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard('app')) {
            return $redirect;
        }

        $request->merge(['queue_via_scheduler' => $request->boolean('queue_via_scheduler')]);
        $this->remember('app', $request->validate(InstallRules::app()));
        $this->complete('app');

        return redirect()->route('install.mail');
    }

    public function mail(SiteManifest $manifest): View|RedirectResponse
    {
        return $this->guard('mail') ?? $this->page('mail', [
            'values' => session('install.data.mail') ?? $manifest->defaults('mail') + ['port' => 587],
        ]);
    }

    public function saveMail(Request $request, MailProbe $probe): RedirectResponse
    {
        if ($redirect = $this->guard('mail')) {
            return $redirect;
        }

        if ($request->input('action') === 'skip') {
            $this->remember('mail', null);
            $this->complete('mail');

            return redirect()->route('install.admin');
        }

        $mail = $request->validate(InstallRules::mail());

        if ($request->input('action') === 'test') {
            $to = $request->validate(['test_to' => ['required', 'email']])['test_to'];
            $result = $probe->send($mail, $to);

            return redirect()->route('install.mail')->withInput($request->except('password'))
                ->with($result['ok'] ? 'status' : 'error', $result['message']);
        }

        $this->remember('mail', $mail);
        $this->complete('mail');

        return redirect()->route('install.admin');
    }

    public function admin(SiteManifest $manifest): View|RedirectResponse
    {
        return $this->guard('admin') ?? $this->page('admin', [
            'values' => collect(session('install.data.admin') ?? $manifest->defaults('admin'))->except('password')->all(),
        ]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard('admin')) {
            return $redirect;
        }

        $admin = $request->validate(InstallRules::admin() + ['password' => [...InstallRules::admin()['password'], 'confirmed']]);
        unset($admin['password_confirmation']);

        $this->remember('admin', $admin);
        $this->complete('admin');

        return redirect()->route('install.review');
    }

    public function review(SiteManifest $manifest): View|RedirectResponse
    {
        return $this->guard('review') ?? $this->page('review', [
            'data' => session('install.data'),
            'modules' => $manifest->modules(),
        ]);
    }

    public function run(Request $request, InstallationService $installer, SiteManifest $manifest): View|RedirectResponse
    {
        if ($redirect = $this->guard('review')) {
            return $redirect;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $data = (array) session('install.data');
        $result = $installer->run($data);

        if (! $result['status']) {
            return redirect()->route('install.review')->with('error', $result['message']);
        }

        // Nothing typed here (passwords included) outlives the install.
        $request->session()->invalidate();

        return view('install.finish', [
            'steps' => self::STEPS,
            'current' => null,
            'basePath' => base_path(),
            'cronLine' => $manifest->cronLine(base_path()),
            'queueViaScheduler' => (bool) ($data['app']['queue_via_scheduler'] ?? false),
            'loginUrl' => rtrim($data['app']['url'], '/').'/app/login',
        ]);
    }

    private function page(string $step, array $data = []): View
    {
        return view('install.'.$step, $data + ['steps' => self::STEPS, 'current' => $step]);
    }

    /**
     * Redirect to the first unfinished earlier step, or null to carry on.
     */
    private function guard(string $step): ?RedirectResponse
    {
        $open = $this->firstOpenStep();

        return array_search($open, self::STEPS, true) < array_search($step, self::STEPS, true)
            ? redirect()->route('install.'.$open)
            : null;
    }

    private function firstOpenStep(): string
    {
        $done = (array) session('install.done', []);

        foreach (self::STEPS as $step) {
            if (! in_array($step, $done, true)) {
                return $step;
            }
        }

        return 'review';
    }

    private function complete(string $step): void
    {
        session()->put('install.done', array_values(array_unique([...(array) session('install.done', []), $step])));
    }

    private function remember(string $section, ?array $values): void
    {
        session()->put('install.data.'.$section, $values);
    }
}
