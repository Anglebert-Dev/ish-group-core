<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\ReleaseService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Nwidart\Modules\Facades\Module;

class ReleaseController extends Controller
{
    public function index(SiteService $sites, SigningKeys $keys): View
    {
        $modules = $sites->assignableModules();
        $latest = Release::latestFor($modules);

        return view('distribution::releases.index', [
            'releases' => Release::query()->latest('id')->paginate(25),
            'testSites' => Site::query()->where('is_test', true)->count(),
            'modules' => collect($modules)->map(fn (string $name) => [
                'name' => $name,
                'version' => (string) Module::find($name)?->get('version', '1.0.0'),
                'published' => $latest[$name]->version ?? null,
            ])->all(),
            'keysConfigured' => $keys->configured(),
            'publicKey' => $keys->configured() ? $keys->publicKey() : null,
        ]);
    }

    public function store(Request $request, ReleaseService $releases): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string'],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ]);

        $result = $releases->publish($data['module'], $data['changelog'] ?? null, $request->boolean('test_only'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Create the signing key from the web. Only when there is none: replacing
     * a key breaks updates for sites built with the old one, so that stays a
     * deliberate terminal step (distribution:keygen --force).
     */
    public function createKey(SigningKeys $keys): RedirectResponse
    {
        if ($keys->configured()) {
            return back()->with('error', __('distribution::distribution.keys_exist_web'));
        }

        try {
            $pair = $keys->create();
        } catch (InvalidArgumentException) {
            $pair = SigningKeys::generate();

            // .env not writable: hand over the lines to add by hand.
            return back()->with('error', __('distribution::distribution.keys_env_not_writable'))
                ->with('signing_env_lines', "DISTRIBUTION_SIGNING_SECRET={$pair['secret']}\nDISTRIBUTION_PUBLIC_KEYS={$pair['public']}");
        }

        // Shown once, so it can be backed up.
        return back()->with('success', __('distribution::distribution.keys_created_web'))->with('signing_secret', $pair['secret']);
    }

    public function toEveryone(Release $release, ReleaseService $releases): RedirectResponse
    {
        $result = $releases->releaseToEveryone($release);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function yank(Release $release, ReleaseService $releases): RedirectResponse
    {
        $result = $releases->yank($release);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
