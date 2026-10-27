<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff equivalent of Laravel's password.confirm, bound to the staff guard.
 *
 * For a form submission (POST/PUT/DELETE) the action itself is remembered,
 * so after the password is confirmed it is submitted automatically instead
 * of making the user click it again (see AuthController::confirmPassword).
 */
class ConfirmStaffPassword
{
    public const PENDING_KEY = 'auth.staff_pending_action';

    /** Seconds a remembered action stays valid. */
    public const PENDING_TTL = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.staff_password_confirmed_at', 0);
        $timeout = (int) config('auth.password_timeout', 10800);

        if (is_numeric($confirmedAt) && (time() - (int) $confirmedAt) < $timeout) {
            return $next($request);
        }

        $request->session()->forget(self::PENDING_KEY);

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        } elseif ($request->allFiles() === []) {
            $request->session()->put(self::PENDING_KEY, [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                // Encrypted: the input may hold a new user's password.
                'input' => Crypt::encrypt($request->except(['_token', '_method'])),
                'back' => $request->headers->get('referer') ?: url()->previous(),
                'at' => time(),
            ]);
        } elseif ($request->headers->get('referer')) {
            // Uploaded files can't be carried over; return to the form.
            $request->session()->put('url.intended', $request->headers->get('referer'));
        }

        return redirect()->route('staff.password.confirm');
    }
}
