<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Runs on every staff request — not only at login — so suspending a user
 * or deactivating a business takes effect on their very next click
 * (reference doc pitfall 2).
 */
class EnsureBusinessActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('staff')->user();

        if (! $user) {
            return $next($request);
        }

        $error = match (true) {
            $user->isBanned() => __('body.account_suspended'),
            ! $user->isApproved() => __('body.account_awaiting_approval'),
            ! $user->isSuperAdmin() && ! $user->business?->is_active => __('body.business_inactive'),
            default => null,
        };

        if ($error === null) {
            return $next($request);
        }

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login')->withErrors(['email' => $error]);
    }
}
