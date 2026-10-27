<?php

namespace App\Http\Middleware;

use App\Services\SuperAdmin\ImpersonationService;
use Closure;
use Illuminate\Http\Request;

/**
 * Account-security changes (password, MFA) are off-limits while a
 * superadmin is signed in as someone else.
 */
class PreventWhileImpersonating
{
    public function __construct(protected ImpersonationService $impersonation)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->impersonation->isImpersonating($request)) {
            return back()->with('error', __('body.impersonation_blocked_action'));
        }

        return $next($request);
    }
}
