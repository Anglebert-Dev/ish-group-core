<?php

namespace App\Helpers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    /**
     * Check if the authenticated user has a permission.
     * Supports pipe-separated OR logic: "create programs.program|update programs.program"
     */
    public static function can(string $permission): bool
    {
        if (! auth('staff')->check()) {
            return false;
        }

        $user = auth('staff')->user();

        $permissions = explode('|', $permission);

        if ($user->hasRole('SuperAdmin')) {
            foreach ($permissions as $perm) {
                $name = trim($perm);
                if (in_array($name, self::superAdminDeniedPermissions(), true)) {
                    continue;
                }

                return true;
            }

            return false;
        }

        foreach ($permissions as $perm) {
            if ($user->hasPermissionTo(trim($perm))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only one portal signed in on this browser session.
     * Staff and customer share the same session cookie, so logging into
     * one portal must clear the other.
     */
    public static function claimExclusivePortal(string $guard): void
    {
        $other = match ($guard) {
            'staff' => 'customer',
            'customer' => 'staff',
            default => null,
        };

        if ($other === null) {
            return;
        }

        if (Auth::guard($other)->check()) {
            Auth::guard($other)->logout();
        }
    }

    /**
     * Permissions the SuperAdmin bypass should never grant, even though it
     * otherwise grants everything. Empty for now — kept as a hook for any
     * future permission that must stay opt-in even for SuperAdmin.
     *
     * @return list<string>
     */
    public static function superAdminDeniedPermissions(): array
    {
        return [];
    }

    /**
     * Require a permission — throws HttpResponseException if user lacks it.
     */
    public static function require(string $permission): void
    {
        if (! static::can($permission)) {
            throw new HttpResponseException(
                redirect()->back()->withErrors(['error' => __('body.no_permission')])
            );
        }
    }

    /**
     * Shorthand alias.
     */
    public static function user()
    {
        return auth('staff')->user();
    }
}
