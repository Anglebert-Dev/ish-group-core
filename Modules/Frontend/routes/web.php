<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\Admin\MessageController;
use Modules\Frontend\Http\Controllers\Admin\WebsiteController;
use Modules\Frontend\Http\Controllers\ContactController;
use Modules\Frontend\Http\Controllers\CustomerAuthController;
use Modules\Frontend\Http\Controllers\CustomerProfileController;
use Modules\Frontend\Http\Controllers\SiteController;

/*
|--------------------------------------------------------------------------
| Frontend module
|--------------------------------------------------------------------------
| Loaded with ['web', 'module:Frontend'] (see RouteServiceProvider), so
| every route here 404s while the module is not installed.
|
| Public sites:  /s/{site}/...  (one per business; ResolveSite sets the
|                business, its branding and URL defaults)
| "/":           the primary business's site, via DataController::landing_page()
| Staff editor:  /app/website, /app/website/messages
*/

Route::prefix('s/{site}')->middleware('frontend.site')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('site.home');

    foreach (SiteController::PAGES as $page) {
        Route::get('/'.$page, [SiteController::class, 'page'])->defaults('page', $page)->name($page);
    }

    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.send');

    Route::middleware(['guest:customer', 'frontend.accounts:register'])->group(function () {
        Route::get('/register', [CustomerAuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [CustomerAuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('register.store');
    });

    Route::middleware(['guest:customer', 'frontend.accounts:login'])->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [CustomerAuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login.store');
        Route::get('/forgot-password', [CustomerAuthController::class, 'showForgotPasswordForm'])->name('customer.password.request');
        Route::post('/forgot-password', [CustomerAuthController::class, 'sendResetLink'])
            ->middleware('throttle:password-reset')
            ->name('customer.password.email');
        Route::get('/reset-password/{token}', [CustomerAuthController::class, 'showResetForm'])->name('customer.password.reset');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
            ->middleware('throttle:password-reset')
            ->name('customer.password.update');
    });

    Route::post('/logout', [CustomerAuthController::class, 'logout'])
        ->middleware('auth:customer')
        ->name('logout');

    Route::middleware(['frontend.accounts:login', 'auth:customer', 'auth.session', 'exclusive.portal:customer', 'customer.active', 'no-back-cache'])->group(function () {
        Route::get('/account', [CustomerProfileController::class, 'dashboard'])->name('profile');
        Route::get('/account/profile', [CustomerProfileController::class, 'show'])->name('account.profile');
        Route::put('/account/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
        Route::put('/account/profile/password', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');
    });
});

Route::prefix('app/website')->name('frontend.admin.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/view', [WebsiteController::class, 'view'])->middleware('permission:read website.site')->name('website.view');

        Route::get('/messages', [MessageController::class, 'index'])->middleware('permission:list website.message')->name('messages.index');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->middleware('permission:read website.message')->name('messages.show');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->middleware('permission:delete website.message')->name('messages.destroy');

        Route::get('/{tab?}', [WebsiteController::class, 'edit'])->middleware('permission:read website.site')->name('website.edit');
        Route::put('/{tab}', [WebsiteController::class, 'update'])->middleware('permission:update website.site')->name('website.update');
    });
