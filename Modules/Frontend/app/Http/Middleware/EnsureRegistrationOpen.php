<?php

namespace Modules\Frontend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Frontend\Support\SiteContext;

/**
 * Customer sign-up only while the site allows it (Website → General).
 */
class EnsureRegistrationOpen
{
    public function __construct(protected SiteContext $context)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        abort_unless((bool) $this->context->site()?->get('general.allow_registration'), 404);

        return $next($request);
    }
}
