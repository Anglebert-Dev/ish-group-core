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

class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        return view('injongepayconnect::platform.index', [
            'integrators' => Integrator::query()->orderBy('name')->get(),
            'connections' => Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->with(['integrator', 'business'])->latest('id')->get(),
            'businesses' => Business::query()->whereKeyNot((int) System::getProperty('primary_business_id'))->orderBy('name')->pluck('name', 'uuid')->all(),
            'profiles' => array_combine(array_keys(config('injongepayconnect.profiles')), array_keys(config('injongepayconnect.profiles'))),
            'calls' => ApiCall::query()->latest('id')->limit(50)->get(),
            'secret' => session('injongepayconnect_secret'),
        ]);
    }

    public function storeIntegrator(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'profile' => ['required', Rule::in(array_keys(config('injongepayconnect.profiles')))],
            'contact_email' => ['nullable', 'email'],
        ]);
        Integrator::query()->create($data + ['is_enabled' => true]);

        return back()->with('success', __('injongepayconnect::connect.integrator_saved'));
    }

    public function storeConnection(Request $request, Credentials $credentials): RedirectResponse
    {
        $data = $request->validate(['integrator' => ['required', Rule::exists('ijpc_integrators', 'uuid')], 'business' => ['required', Rule::exists('businesses', 'uuid')]]);
        [$connection, $secret] = $credentials->create(Integrator::query()->whereUuid($data['integrator'])->firstOrFail(), Business::query()->whereUuid($data['business'])->firstOrFail());

        return back()->with('injongepayconnect_secret', ['client_id' => $connection->client_id, 'secret' => $secret])->with('success', __('injongepayconnect::connect.connection_created'));
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
