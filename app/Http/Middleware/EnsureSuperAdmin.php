<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Platform-level area (/app/super-admin/*): only users flagged is_superadmin.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('staff')->user();

        if (! $user?->isSuperAdmin()) {
            Log::warning('authz.superadmin_denied', [
                'user_id' => $user?->id,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(403, __('body.no_permission'));
        }

        return $next($request);
    }
}
