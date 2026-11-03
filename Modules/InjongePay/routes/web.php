<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePay\Http\Controllers\Business\OverviewController;
use Modules\InjongePay\Http\Controllers\Platform\BusinessController;
use Modules\InjongePay\Http\Controllers\Platform\SettingsController;

/*
|--------------------------------------------------------------------------
| Injonge Pay: /app/injonge-pay/... (a business's own panel) and
| /app/injonge-pay/platform/... (the operator, across businesses)
|--------------------------------------------------------------------------
*/

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'];

Route::prefix('app/injonge-pay')->name('injongepay.')->middleware($staff)->group(function () {
    Route::get('/', [OverviewController::class, 'index'])->middleware('permission:list injongepay.overview')->name('overview');
});

Route::prefix('app/injonge-pay/platform')->name('injongepay.platform.')->middleware($staff)->group(function () {
    Route::get('/businesses', [BusinessController::class, 'index'])->middleware('permission:list injongepay_platform.business')->name('businesses.index');
    Route::get('/businesses/{business}', [BusinessController::class, 'edit'])->middleware('permission:read injongepay_platform.business')->name('businesses.edit');
    Route::put('/businesses/{business}', [BusinessController::class, 'update'])->middleware(['permission:update injongepay_platform.business', 'password.confirm.staff'])->name('businesses.update');

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:update injongepay_platform.setting|update injongepay_platform.fee')->name('settings');
    Route::put('/settings/fees', [SettingsController::class, 'updateFees'])->middleware(['permission:update injongepay_platform.fee', 'password.confirm.staff'])->name('settings.fees');
    Route::post('/settings/webhook-secret', [SettingsController::class, 'generateSecret'])->middleware(['permission:update injongepay_platform.setting', 'password.confirm.staff'])->name('settings.secret');
});
