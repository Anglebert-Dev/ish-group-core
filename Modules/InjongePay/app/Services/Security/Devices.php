<?php

namespace Modules\InjongePay\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Modules\InjongePay\Models\TrustedDevice;

/**
 * Browsers a person has proved with a step-up. A money movement from any
 * other browser needs a step-up first.
 */
class Devices
{
    public const COOKIE = 'ijp_device';

    public function isTrusted(Request $request, User $user): bool
    {
        $token = (string) $request->cookie(self::COOKIE);

        if ($token === '') {
            return false;
        }

        $device = TrustedDevice::query()->where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first();
        $device?->forceFill(['last_used_at' => now(), 'ip' => $request->ip()])->save();

        return $device !== null;
    }

    public function trust(Request $request, User $user): void
    {
        $token = Str::random(64);
        TrustedDevice::query()->create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token),
            'label' => mb_substr((string) $request->userAgent(), 0, 250), 'ip' => $request->ip(), 'last_used_at' => now(),
        ]);
        Cookie::queue(Cookie::make(self::COOKIE, $token, 60 * 24 * 180, null, null, null, true, false, 'lax'));
        audit('injongepay_device_trusted', __('injongepay::audit.injongepay_device_trusted').': '.$user->name, $user, ['ip' => $request->ip()], $user->business_id);
    }

    public function revoke(TrustedDevice $device): void
    {
        $device->forceFill(['revoked_at' => now()])->save();
        audit('injongepay_device_revoked', __('injongepay::audit.injongepay_device_revoked'), null, ['device' => $device->uuid, 'user_id' => $device->user_id]);
    }
}
