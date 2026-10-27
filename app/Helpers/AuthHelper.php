<?php

namespace App\Helpers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    /**
     * Check if the authenticated staff user has a permission.
     * Supports pipe-separated OR logic: "create users.user|update users.user".
     *
     * Goes through the Gate, so Gate::before (superadmin / business Admin)
     * applies — see AppServiceProvider.
     */
    public static function can(string $permission): bool
    {
        $user = auth('staff')->user();

        if (! $user) {
            return false;
        }

        foreach (explode('|', $permission) as $perm) {
            if ($user->can(trim($perm))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only one portal signed in on this browser session.
     * Staff and customer share the same session cookie, so logging into
     * one portal must clear the other. The customer guard only exists while
     * the Frontend module is installed.
     */
    public static function claimExclusivePortal(string $guard): void
    {
        $other = match ($guard) {
            'staff' => 'customer',
            'customer' => 'staff',
            default => null,
        };

        if ($other === null || ! config("auth.guards.{$other}")) {
            return;
        }

        if (Auth::guard($other)->check()) {
            Auth::guard($other)->logout();
        }
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
