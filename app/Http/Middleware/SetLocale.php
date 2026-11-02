<?php

namespace App\Http\Middleware;

use App\Services\Setting\SettingService;
use App\Support\Installer\Installer;
use App\Support\Locale\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every page in the right language (App\Support\Locale\Locales).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before install there is no database: the app default stays.
        if (! Installer::isInstalled()) {
            return $next($request);
        }

        $user = $request->user('staff');
        $business = $user?->business ?? app(SettingService::class)->current();

        app()->setLocale(Locales::resolve($user, $business, $request->hasSession() ? $request->session()->get(Locales::SESSION) : null));

        return $next($request);
    }
}
