<?php

namespace Modules\InjongePayConnect\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Models\System\System;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\ApiCall;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Services\Credentials;

class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $connections = Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->with(['integrator', 'business'])
            ->when($request->query('integrator'), fn ($q, $uuid) => $q->whereIn('integrator_id', Integrator::query()->whereUuid($uuid)->select('id')))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_enabled', $s === 'on'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('client_id', 'like', "%{$search}%")
                ->orWhereIn('business_id', Business::query()->where('name', 'like', "%{$search}%")->select('id'))))
            ->latest('id')->paginate(25)->withQueryString();

        return view('injongepayconnect::platform.connections.index', [
            'connections' => $connections,
            'integrators' => Integrator::query()->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('injongepayconnect::platform.connections.create', [
            'integrators' => Integrator::query()->where('is_enabled', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'businesses' => Business::query()->whereKeyNot((int) System::getProperty('primary_business_id'))->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function store(Request $request, Credentials $credentials): RedirectResponse
    {
        $data = $request->validate(['integrator' => ['required', Rule::exists('ijpc_integrators', 'uuid')], 'business' => ['required', Rule::exists('businesses', 'uuid')]]);
        [$connection, $secret] = $credentials->create(Integrator::query()->whereUuid($data['integrator'])->firstOrFail(), Business::query()->whereUuid($data['business'])->firstOrFail());

        return redirect()->route('injongepayconnect.platform.connections.show', $connection->uuid)
            ->with('injongepayconnect_secret', ['client_id' => $connection->client_id, 'secret' => $secret])->with('success', __('injongepayconnect::connect.connection_created'));
    }

    public function show(string $connection): View
    {
        $connection = $this->find($connection);

        return view('injongepayconnect::platform.connections.show', [
            'connection' => $connection->load(['integrator', 'business']),
            'calls' => ApiCall::query()->where('connection_id', $connection->id)->latest('id')->paginate(25),
            'secret' => session('injongepayconnect_secret'),
        ]);
    }

    public function rotate(string $connection, Credentials $credentials): RedirectResponse
    {
        $connection = $this->find($connection);

        return back()->with('injongepayconnect_secret', ['client_id' => $connection->client_id, 'secret' => $credentials->rotate($connection)])->with('success', __('injongepayconnect::connect.rotated'));
    }

    public function toggle(string $connection, Credentials $credentials): RedirectResponse
    {
        $connection = $this->find($connection);
        $credentials->setEnabled($connection, ! $connection->is_enabled);

        return back()->with('success', __('injongepayconnect::connect.toggled'));
    }

    public function limits(Request $request, string $connection): RedirectResponse
    {
        $data = $request->validate(['rate_limit' => ['nullable', 'integer', 'min:10', 'max:10000'], 'allowed_ips' => ['nullable', 'string', 'max:500']]);
        $this->find($connection)->forceFill(['rate_limit' => $data['rate_limit'] ?? null, 'allowed_ips' => $data['allowed_ips'] ?? null])->save();

        return back()->with('success', __('injongepayconnect::connect.limits_saved'));
    }

    private function find(string $uuid): Connection
    {
        return Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereUuid($uuid)->firstOrFail();
    }
}
