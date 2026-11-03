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

   
    public function setPin(Request $request, Pins $pins, StepUp $stepUp): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'confirmed'], 'code' => ['nullable', 'string', 'max:10']]);
        $user = $request->user('staff');

        try {
            if ($pins->has($user)) {
                if (blank($data['code'] ?? null)) {
                    $stepUp->send($user, 'pin_change');

                    return back()->with('pin_step_up', $stepUp->method($user))->with('error', __('injongepay::injongepay.step_up_needed_'.$stepUp->method($user)));
                }

                $stepUp->check($user, 'pin_change', $data['code']);
            }

            $pins->set($user, $data['pin']);
        } catch (InjongePayException $e) {
            return back()->with('pin_step_up', $pins->has($user) ? $stepUp->method($user) : null)->with('error', $e->getMessage());
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
