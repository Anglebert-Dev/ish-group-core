<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\NormalizeFormattedAmounts::class);

        $middleware->alias([
            'approved' => \App\Http\Middleware\EnsureUserIsApproved::class,
            'customer.active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'no-back-cache' => \App\Http\Middleware\PreventBackHistoryCache::class,
            'exclusive.portal' => \App\Http\Middleware\EnsureExclusivePortalSession::class,
            'password.confirm.staff' => \App\Http\Middleware\ConfirmStaffPassword::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('app') || $request->is('app/*') || $request->is('super-admin/*')
                ? route('staff.login')
                : route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->is('app') || $request->is('app/*') || $request->is('super-admin/*')
                ? route('dashboard')
                : route('profile');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
