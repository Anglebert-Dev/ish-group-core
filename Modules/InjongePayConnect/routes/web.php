<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePayConnect\Http\Controllers\Business\ConnectionController;
use Modules\InjongePayConnect\Http\Controllers\Platform\PartnerController;

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'];

Route::prefix('app/injonge-pay/integrations')->name('injongepayconnect.')->middleware($staff)->group(function () {
    Route::get('/', [ConnectionController::class, 'index'])->middleware('permission:list injongepay_connect.connection|list injongepay_connect.invoice')->name('connections');
});

Route::prefix('app/injonge-pay/platform/partners')->name('injongepayconnect.platform.')->middleware($staff)->group(function () {
    Route::get('/', [PartnerController::class, 'index'])->middleware('permission:list injongepay_connect_platform.connection')->name('index');
    Route::post('/integrators', [PartnerController::class, 'storeIntegrator'])->middleware(['permission:create injongepay_connect_platform.integrator', 'password.confirm.staff'])->name('integrators.store');
    Route::post('/connections', [PartnerController::class, 'storeConnection'])->middleware(['permission:create injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.store');
    Route::post('/connections/{connection}/rotate', [PartnerController::class, 'rotate'])->middleware(['permission:update injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.rotate');
    Route::post('/connections/{connection}/toggle', [PartnerController::class, 'toggle'])->middleware(['permission:update injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.toggle');
    Route::put('/connections/{connection}/limits', [PartnerController::class, 'limits'])->middleware('permission:update injongepay_connect_platform.connection')->name('connections.limits');
});
