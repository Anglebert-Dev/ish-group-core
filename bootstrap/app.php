<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ConfirmStaffPassword;
use App\Http\Middleware\EnsureBusinessActive;
use App\Http\Middleware\EnsureExclusivePortalSession;
use App\Http\Middleware\EnsureModuleActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureWithinQuota;
use App\Http\Middleware\NormalizeFormattedAmounts;
use App\Http\Middleware\PreventBackHistoryCache;
use App\Http\Middleware\PreventWhileImpersonating;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Installer\PreInstall;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// A fresh copy (not installed yet): .env, APP_KEY and file sessions before
// Laravel reads its configuration. Does nothing once installed.
PreInstall::prepare(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('install')->group(base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedirectToInstaller::class);
        $middleware->append(SecurityHeaders::class);

        // The installer runs before there is a database: session and CSRF only.
        $middleware->group('install', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);
        $middleware->appendToGroup('web', NormalizeFormattedAmounts::class);
        $middleware->appendToGroup('web', SetLocale::class);

        $middleware->alias([
            'business.active' => EnsureBusinessActive::class,
            'superadmin' => EnsureSuperAdmin::class,
            'not.impersonating' => PreventWhileImpersonating::class,
            'module' => EnsureModuleActive::class,
            'quota' => EnsureWithinQuota::class,
            'permission' => CheckPermission::class,
            'no-back-cache' => PreventBackHistoryCache::class,
            'exclusive.portal' => EnsureExclusivePortalSession::class,
            'password.confirm.staff' => ConfirmStaffPassword::class,
            'auth.session' => AuthenticateSession::class,
        ]);

        // Customer routes only exist while the Frontend module is installed;
        // everything else belongs to the staff app.
        // Module routes may need URL defaults set by their own middleware
        // (e.g. Frontend's {site}); fall back to the staff app otherwise.
        $middleware->redirectGuestsTo(function (Request $request) {
            return ! $request->is('app', 'app/*') && Route::has('login')
                ? rescue(fn () => route('login'), fn () => route('staff.login'), false)
                : route('staff.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return ! $request->is('app', 'app/*') && Route::has('profile')
                ? rescue(fn () => route('profile'), fn () => route('dashboard'), false)
                : route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
