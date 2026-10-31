<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// A fresh copy (not installed yet): .env, APP_KEY and file sessions before
// Laravel reads its configuration. Does nothing once installed.
App\Support\Installer\PreInstall::prepare(dirname(__DIR__));

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
        $middleware->prepend(\App\Http\Middleware\RedirectToInstaller::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // The installer runs before there is a database: session and CSRF only.
        $middleware->group('install', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\NormalizeFormattedAmounts::class);

        $middleware->alias([
            'business.active' => \App\Http\Middleware\EnsureBusinessActive::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'not.impersonating' => \App\Http\Middleware\PreventWhileImpersonating::class,
            'module' => \App\Http\Middleware\EnsureModuleActive::class,
            'quota' => \App\Http\Middleware\EnsureWithinQuota::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'no-back-cache' => \App\Http\Middleware\PreventBackHistoryCache::class,
            'exclusive.portal' => \App\Http\Middleware\EnsureExclusivePortalSession::class,
            'password.confirm.staff' => \App\Http\Middleware\ConfirmStaffPassword::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
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
