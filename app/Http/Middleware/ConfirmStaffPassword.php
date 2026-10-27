<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff equivalent of Laravel's password.confirm, bound to the staff guard.
 *
 * The user is sent back to the page they were on, where a password pop-up
 * opens. A form submission (POST/PUT/DELETE) is remembered and submitted
 * again automatically once confirmed (see AuthController::confirmPassword).
 */
class ConfirmStaffPassword
{
    public const PENDING_KEY = 'auth.staff_pending_action';

    /** Flash flag that opens the password pop-up on the next page. */
    public const MODAL_FLAG = 'confirm_password_modal';

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
        $back = $request->headers->get('referer');

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        } elseif ($request->allFiles() === []) {
            $request->session()->put(self::PENDING_KEY, [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                // Encrypted: the input may hold a new user's password.
                'input' => Crypt::encrypt($request->except(['_token', '_method'])),
                'back' => $back ?: url()->previous(),
                'at' => time(),
            ]);
        }

        // Ask on the page the user is on (password pop-up); the full
        // confirmation page is only for direct links with nowhere to return.
        if ($back && ! str_contains($back, '/confirm-password')) {
            return redirect()->to($back)->with(self::MODAL_FLAG, true);
        }

        return redirect()->route('staff.password.confirm');
    }
}
