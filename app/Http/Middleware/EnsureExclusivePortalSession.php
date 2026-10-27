<?php

namespace App\Http\Middleware;

use App\Helpers\AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff and customer share one browser session. If both guards are somehow
 * authenticated, keep only the portal for the current route surface.
 */
class EnsureExclusivePortalSession
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        AuthHelper::claimExclusivePortal($guard);

        return $next($request);
    }
}
