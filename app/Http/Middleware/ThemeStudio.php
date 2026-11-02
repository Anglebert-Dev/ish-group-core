<?php

namespace App\Http\Middleware;

use App\Helpers\AuthHelper;
use App\Services\Branding\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The branding studio's live preview (docs/branding.md §3.15): the page in the
 * preview frame carries the unsaved form in ?studio= (base64 JSON). Used only
 * for someone who may edit branding, only for their own business, only for
 * this request. Nothing is saved; nobody else sees it.
 */
class ThemeStudio
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never carried over from an earlier request (long-running workers, tests).
        app(ThemeService::class)->clearStudio();

        $encoded = $request->query('studio');
        $user = $request->user('staff');

        if (is_string($encoded) && strlen($encoded) <= 20000 && $user && AuthHelper::can('update settings.setting')) {
            $payload = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);

            if (is_array($payload)) {
                $appearance = $request->query('appearance');
                app(ThemeService::class)->useStudio($user->business_id, $payload + (in_array($appearance, ['light', 'dark'], true) ? ['_appearance' => $appearance] : []));
            }
        }

        return $next($request);
    }
}
