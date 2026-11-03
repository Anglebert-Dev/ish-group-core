<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\TrustedDevice;
use Modules\InjongePay\Services\Security\Devices;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\Security\StepUp;

/** The person's own transaction PIN and trusted browsers. */
class SecurityController extends Controller
{
    public function show(Request $request, Pins $pins, StepUp $stepUp): View
    {
        $user = $request->user('staff');

        return view('injongepay::business.security', [
            'hasPin' => $pins->has($user),
            'method' => $stepUp->method($user),
            'devices' => TrustedDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->latest('last_used_at')->get(),
        ]);
    }

    public function setPin(Request $request, Pins $pins): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'confirmed']]);

        try {
            $pins->set($request->user('staff'), $data['pin']);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.pin_saved'));
    }

    public function revoke(Request $request, TrustedDevice $device, Devices $devices): RedirectResponse
    {
        abort_unless($device->user_id === $request->user('staff')->id, 404);
        $devices->revoke($device);

        return back()->with('success', __('injongepay::injongepay.device_revoked'));
    }
}
