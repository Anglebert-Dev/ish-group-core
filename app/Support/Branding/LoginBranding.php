<?php

namespace App\Support\Branding;

use App\Models\Business\Business;
use App\Models\Business\BusinessSetting;
use Illuminate\Support\Facades\Cookie;

/**
 * Which business's brand the sign-in pages wear (docs/branding.md §3.6).
 * The login page is shared by every business, so it learns the business
 * from the business login link (/app/login/{handle}), then remembers it
 * (session, and a cookie set at sign-in) so plain /app/login keeps it.
 *
 * Only the look changes: none of this plays any part in signing in.
 * Registration creates a new business, so it keeps the platform brand.
 */
final class LoginBranding
{
    public const KEY = 'login_business';

    /** Pages that wear the remembered business's brand. */
    public const ROUTES = ['staff.login', 'staff.login.store', 'staff.business-login', 'staff.mfa.*', 'staff.password.*'];

    public const HANDLE_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    /** The business for this sign-in page, if one is remembered. */
    public function business(): ?Business
    {
        $request = request();
        if (! $request->route() || ! $request->routeIs(...self::ROUTES) || auth('staff')->check()) {
            return null;
        }

        $id = ($request->hasSession() ? $request->session()->get(self::KEY) : null) ?? $request->cookie(self::KEY);

        return is_numeric($id) ? Business::query()->where('is_active', true)->find((int) $id) : null;
    }

    public function remember(Business $business): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::KEY, $business->id);
        }
        Cookie::queue(self::KEY, (string) $business->id, 60 * 24 * 365);
    }

    public function findByHandle(string $handle): ?Business
    {
        $setting = BusinessSetting::query()->where('key', 'branding.login_handle')->get()
            ->first(fn (BusinessSetting $row) => $row->value === strtolower($handle));

        return $setting ? Business::query()->where('is_active', true)->find($setting->business_id) : null;
    }

    public function handleTaken(string $handle, Business $except): bool
    {
        return BusinessSetting::query()->where('key', 'branding.login_handle')->where('business_id', '!=', $except->id)->get()
            ->contains(fn (BusinessSetting $row) => $row->value === strtolower($handle));
    }
}
