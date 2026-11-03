<?php

namespace App\Http\Middleware;

use App\Enums\ActivityLogAction;
use App\Models\System\System;
use App\Services\ActivityLog\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class LogoutIdleStaff
{
    public const SESSION_KEY = 'auth.staff_last_activity_at';

    // Covers the gap between the browser's last ping and the deadline.
    private const GRACE_SECONDS = 15;

    public function __construct(protected ActivityLogService $audit)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('staff')->user();
        $minutes = $user && $request->hasSession() ? (int) System::getProperty('idle_timeout_minutes', '0') : 0;

        if ($minutes <= 0) {
            return $next($request);
        }

        $last = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($last > 0 && time() - $last > $minutes * 60 + self::GRACE_SECONDS) {
            $this->audit->log(
                ActivityLogAction::Logout,
                __('body.audit_log_idle_logout', ['name' => $user->name, 'minutes' => $minutes]),
                $user,
                ['guard' => 'staff', 'reason' => 'idle', 'idle_minutes' => $minutes],
                $user->business_id,
                $user,
            );

            // One audit row (above) instead of the generic sign-out one.
            ActivityLogService::muted(fn () => Auth::guard('staff')->logout());
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = __('body.idle_signed_out', ['minutes' => $minutes]);

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'idle' => true], 401)
                : redirect()->route('staff.login')->with('error', $message);
        }

        if ($last === 0 || ! $request->headers->has('X-Idle-Passive')) {
            $request->session()->put(self::SESSION_KEY, time());
        }

        return $next($request);
    }
}
