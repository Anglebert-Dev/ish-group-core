<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Without this, the browser can serve a stale, already-authenticated page
 * straight from its back/forward cache after logout — the request never
 * reaches the server, so the session-destroyed redirect never fires.
 */
class PreventBackHistoryCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
