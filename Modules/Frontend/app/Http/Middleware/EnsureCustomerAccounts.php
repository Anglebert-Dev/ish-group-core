<?php

namespace Modules\Frontend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Frontend\Support\SiteContext;

/**
 * Customer login / sign-up only while the business allows it
 * (Website → General → Customer accounts).
 *
 * Usage: 'frontend.accounts:login' or 'frontend.accounts:register'.
 */
class EnsureCustomerAccounts
{
    public function __construct(protected SiteContext $context)
    {
    }

    public function handle(Request $request, Closure $next, string $feature = 'login')
    {
        $site = $this->context->site();

        abort_unless($site && ($feature === 'register' ? $site->allowsRegistration() : $site->allowsLogin()), 404);

        return $next($request);
    }
}
