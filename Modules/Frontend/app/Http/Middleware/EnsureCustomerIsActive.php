<?php

namespace Modules\Frontend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->isBanned()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('body.account_suspended')]);
        }

        return $next($request);
    }
}
