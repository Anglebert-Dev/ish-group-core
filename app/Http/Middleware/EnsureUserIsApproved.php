<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('staff')->user();

        if ($user && $user->isBanned()) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            return redirect()->route('staff.login')
                ->withErrors(['email' => __('body.account_suspended')]);
        }

        if ($user && ! $user->isApproved()) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            return redirect()->route('staff.login')
                ->withErrors(['email' => __('body.account_awaiting_approval')]);
        }

        return $next($request);
    }
}
