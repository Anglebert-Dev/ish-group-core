<?php

namespace Modules\Frontend\Http\Middleware;

use App\Services\Module\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Modules\Frontend\Services\SiteService;
use Modules\Frontend\Support\SiteContext;

/**
 * /s/{site}/...: load the business's site and make it the request context.
 * Unpublished sites are only visible to that business's own staff (preview).
 */
class ResolveSite
{
    public function __construct(
        protected SiteService $sites,
        protected SiteContext $context,
        protected ModuleManager $modules,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        // Runs before 'module:Frontend' (it has middleware priority), so it
        // checks the module itself.
        abort_unless($this->modules->isActive('Frontend'), 404);

        $site = $this->sites->findBySlug((string) $request->route('site'));

        abort_unless($site && $site->business && $site->business->is_active, 404);
        abort_unless($site->business->hasModuleEnabled('Frontend'), 404);

        $staff = auth('staff')->user();
        $isOwnStaff = $staff && (int) $staff->business_id === (int) $site->business_id;
        abort_unless($site->is_published || $isOwnStaff, 404);

        $this->context->apply($site);
        $request->route()->forgetParameter('site');

        return $next($request);
    }
}
