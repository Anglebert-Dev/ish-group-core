<?php

namespace App\Http\Middleware;

use App\Contracts\Entitlements;
use App\Support\Entitlements\Quota;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: ->middleware('quota:users') on the route that creates one more.
 */
class EnsureWithinQuota
{
    public function __construct(
        protected Quota $quota,
        protected Entitlements $entitlements,
    ) {
    }

    public function handle(Request $request, Closure $next, string $key)
    {
        $business = auth('staff')->user()?->business;

        if ($business && ! $this->quota->canAdd($business, $key)) {
            $message = __('body.quota_reached', [
                'limit' => $this->quota->limit($business, $key),
                'item' => app(Quota::class)->label($key),
            ]);

            $upgrade = $this->entitlements->upgradeUrl();

            return $upgrade
                ? redirect()->to($upgrade)->with('error', $message)
                : back()->with('error', $message);
        }

        return $next($request);
    }
}
