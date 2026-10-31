<?php

namespace App\Http\Middleware;

use App\Support\Installer\Installer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Global, first. Not installed: every page sends you to /install.
 * Installed: /install no longer exists.
 *
 * A server set up before the installer existed (users in the database, no
 * marker) is recognised and marked installed instead of being redirected.
 */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        $installPage = $request->is('install', 'install/*');

        if (Installer::isInstalled() || $this->alreadySetUp()) {
            abort_if($installPage, 404);

            return $next($request);
        }

        if ($installPage || $request->is('up')) {
            return $next($request);
        }

        return redirect('/install');
    }

    private function alreadySetUp(): bool
    {
        try {
            if (! Schema::hasTable('users') || ! DB::table('users')->exists()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        Installer::markInstalled();

        return true;
    }
}
