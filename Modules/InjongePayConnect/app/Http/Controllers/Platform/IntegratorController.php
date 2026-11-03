<?php

namespace Modules\InjongePayConnect\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Models\Token;

class IntegratorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('injongepayconnect::platform.integrators.index', [
            'integrators' => Integrator::query()->withCount(['connections' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($request->query('profile'), fn ($q, $p) => $q->where('profile', $p))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'profiles' => $this->profiles(),
        ]);
    }

    public function create(): View
    {
        return view('injongepayconnect::platform.integrators.create', ['profiles' => $this->profiles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $integrator = Integrator::query()->create($this->validated($request) + ['is_enabled' => true]);

        return redirect()->route('injongepayconnect.platform.integrators.show', $integrator)->with('success', __('injongepayconnect::connect.integrator_saved'));
    }

    public function show(Integrator $integrator): View
    {
        return view('injongepayconnect::platform.integrators.show', [
            'integrator' => $integrator,
            'connections' => Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->with('business')->where('integrator_id', $integrator->id)->latest('id')->paginate(25),
            'profiles' => $this->profiles(),
        ]);
    }

    public function update(Request $request, Integrator $integrator): RedirectResponse
    {
        $integrator->update($this->validated($request));

        return back()->with('success', __('injongepayconnect::connect.integrator_saved'));
    }

    /** Switching an integrator off stops all its connections at once. */
    public function toggle(Integrator $integrator): RedirectResponse
    {
        $integrator->update(['is_enabled' => ! $integrator->is_enabled]);

        if (! $integrator->is_enabled) {
            Token::query()->whereIn('connection_id', Connection::query()->withoutGlobalScopes()->where('integrator_id', $integrator->id)->select('id'))->delete();
        }

        return back()->with('success', __('injongepayconnect::connect.toggled'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'profile' => ['required', Rule::in(array_keys(config('injongepayconnect.profiles')))],
            'contact_email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return array<string, string> */
    private function profiles(): array
    {
        $keys = array_keys(config('injongepayconnect.profiles'));

        return array_combine($keys, array_map(fn ($k) => __('injongepayconnect::connect.profile_'.str_replace('-', '_', $k)), $keys));
    }
}
