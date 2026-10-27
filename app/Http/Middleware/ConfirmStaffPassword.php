<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff equivalent of Laravel's password.confirm, bound to the staff guard.
 */
class ConfirmStaffPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.staff_password_confirmed_at', 0);
        $timeout = (int) config('auth.password_timeout', 10800);

        if (is_numeric($confirmedAt) && (time() - (int) $confirmedAt) < $timeout) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        } elseif ($request->headers->get('referer')) {
            $request->session()->put('url.intended', $request->headers->get('referer'));
        }

        return redirect()->route('staff.password.confirm');
    }
}
