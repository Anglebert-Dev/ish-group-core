<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\CustomerAuthController;
use Modules\Frontend\Http\Controllers\CustomerProfileController;

/*
|--------------------------------------------------------------------------
| Frontend module — public website + customer accounts
|--------------------------------------------------------------------------
| Loaded with ['web', 'module:Frontend'] (see RouteServiceProvider), so
| every route here 404s while the module is not installed.
|
| "/" is owned by the core LandingController; this module claims it
| through DataController::landing_page().
*/

Route::view('/about', 'frontend::pages.about')->name('about');
Route::view('/contact', 'frontend::pages.contact')->name('contact');
Route::view('/services', 'frontend::pages.services')->name('services');
Route::view('/privacy', 'frontend::pages.privacy')->name('privacy');
Route::view('/terms', 'frontend::pages.terms')->name('terms');

Route::middleware('guest:customer')->group(function () {
    Route::get('/register', [CustomerAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
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

Route::middleware(['auth:customer', 'auth.session', 'exclusive.portal:customer', 'customer.active', 'no-back-cache'])->group(function () {
    Route::get('/account', [CustomerProfileController::class, 'dashboard'])->name('profile');
    Route::get('/account/profile', [CustomerProfileController::class, 'show'])->name('account.profile');
    Route::put('/account/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::put('/account/profile/password', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');
});
