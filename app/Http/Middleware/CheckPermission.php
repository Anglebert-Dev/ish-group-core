<?php

namespace App\Http\Middleware;

use App\Helpers\AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPermission
{
    /**
     * Usage: ->middleware('permission:list users.role')
     * Supports the same "a.b|c.d" OR syntax as AuthHelper::can().
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (! AuthHelper::can($permission)) {
            Log::warning('authz.permission_denied', [
                'permission' => $permission,
                'user_id' => auth('staff')->id(),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(403, __('body.no_permission'));
        }

        return $next($request);
    }
}
