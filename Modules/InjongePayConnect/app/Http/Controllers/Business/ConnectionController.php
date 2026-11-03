<?php

namespace Modules\InjongePayConnect\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\ApiCall;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Services\Credentials;

/**
 * The business connects its own systems (POS, EBM…) to its Injonge Pay. It
 * chooses one of the integrators Inhills has registered and gets the
 * credentials to give that system.
 */
class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        return view('injongepayconnect::business.connections.index', [
            'connections' => Connection::query()->with('integrator')
                ->when($request->query('status'), fn ($q, $s) => $q->where('is_enabled', $s === 'on'))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('injongepayconnect::business.connections.create', [
            'integrators' => Integrator::query()->where('is_enabled', true)->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function store(Request $request, Credentials $credentials): RedirectResponse
    {
        $data = $request->validate(['integrator' => ['required', Rule::exists('ijpc_integrators', 'uuid')->where('is_enabled', true)]]);
        [$connection, $secret] = $credentials->create(Integrator::query()->whereUuid($data['integrator'])->firstOrFail(), $request->user('staff')->business);

        return redirect()->route('injongepayconnect.connections.show', $connection)
            ->with('injongepayconnect_secret', ['client_id' => $connection->client_id, 'secret' => $secret])->with('success', __('injongepayconnect::connect.connection_created'));
    }

    public function show(Connection $connection): View
    {
        return view('injongepayconnect::business.connections.show', [
            'connection' => $connection->load('integrator'),
            'calls' => ApiCall::query()->where('connection_id', $connection->id)->latest('id')->paginate(25),
            'secret' => session('injongepayconnect_secret'),
        ]);
    }

    public function rotate(Connection $connection, Credentials $credentials): RedirectResponse
    {
        return back()->with('injongepayconnect_secret', ['client_id' => $connection->client_id, 'secret' => $credentials->rotate($connection)])->with('success', __('injongepayconnect::connect.rotated'));
    }

    public function toggle(Connection $connection, Credentials $credentials): RedirectResponse
    {
        $credentials->setEnabled($connection, ! $connection->is_enabled);

        return back()->with('success', __('injongepayconnect::connect.toggled'));
    }

    public function addresses(Request $request, Connection $connection): RedirectResponse
    {
        $data = $request->validate(['allowed_ips' => ['nullable', 'string', 'max:500']]);
        $connection->forceFill(['allowed_ips' => $data['allowed_ips'] ?? null])->save();

        return back()->with('success', __('injongepayconnect::connect.limits_saved'));
    }
}
