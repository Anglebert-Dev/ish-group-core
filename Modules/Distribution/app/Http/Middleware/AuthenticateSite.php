<?php

namespace Modules\Distribution\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Distribution\Models\SiteToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Update API: "Authorization: Bearer ish_…". The token must exist (by its
 * hash), not be revoked, and belong to an active site — whose uuid, when
 * the request names one, must match. The site is put on the request.
 */
class AuthenticateSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $row = $token ? SiteToken::query()->with('site')->where('token_hash', SiteToken::hash($token))->first() : null;

        if (! $row || $row->isRevoked() || ! $row->site) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if (! $row->site->isActive()) {
            return response()->json(['message' => 'Site suspended.'], 403);
        }

        if ($request->filled('site_uuid') && ! hash_equals($row->site->uuid, (string) $request->input('site_uuid'))) {
            return response()->json(['message' => 'Token does not belong to this site.'], 403);
        }

        $row->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('distribution_site', $row->site);

        return $next($request);
    }
}
