<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Distribution\Models\Release;
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

        $result = $releases->publish($data['module'], $data['changelog'] ?? null);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function yank(Release $release, ReleaseService $releases): RedirectResponse
    {
        $result = $releases->yank($release);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
