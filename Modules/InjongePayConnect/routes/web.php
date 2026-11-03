<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePayConnect\Http\Controllers\Business\ConnectionController as BusinessConnectionController;
use Modules\InjongePayConnect\Http\Controllers\Platform\CallController;
use Modules\InjongePayConnect\Http\Controllers\Platform\ConnectionController;
use Modules\InjongePayConnect\Http\Controllers\Platform\IntegratorController;

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'];

Route::prefix('app/injonge-pay/integrations')->name('injongepayconnect.')->middleware($staff)->group(function () {
    Route::get('/', [BusinessConnectionController::class, 'index'])->middleware('permission:list injongepay_connect.connection|list injongepay_connect.invoice')->name('connections');
});

Route::prefix('app/injonge-pay/platform/partners')->name('injongepayconnect.platform.')->middleware($staff)->group(function () {
    Route::redirect('/', '/app/injonge-pay/platform/partners/connections')->name('index');

    Route::get('/connections', [ConnectionController::class, 'index'])->middleware('permission:list injongepay_connect_platform.connection')->name('connections.index');
    Route::get('/connections/create', [ConnectionController::class, 'create'])->middleware('permission:create injongepay_connect_platform.connection')->name('connections.create');
    Route::post('/connections', [ConnectionController::class, 'store'])->middleware(['permission:create injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.store');
    Route::get('/connections/{connection}', [ConnectionController::class, 'show'])->middleware('permission:read injongepay_connect_platform.connection')->name('connections.show');
    Route::post('/connections/{connection}/rotate', [ConnectionController::class, 'rotate'])->middleware(['permission:update injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.rotate');
    Route::post('/connections/{connection}/toggle', [ConnectionController::class, 'toggle'])->middleware(['permission:update injongepay_connect_platform.connection', 'password.confirm.staff'])->name('connections.toggle');
    Route::put('/connections/{connection}/limits', [ConnectionController::class, 'limits'])->middleware('permission:update injongepay_connect_platform.connection')->name('connections.limits');

    Route::get('/integrators', [IntegratorController::class, 'index'])->middleware('permission:list injongepay_connect_platform.integrator')->name('integrators.index');
    Route::get('/integrators/create', [IntegratorController::class, 'create'])->middleware('permission:create injongepay_connect_platform.integrator')->name('integrators.create');
    Route::post('/integrators', [IntegratorController::class, 'store'])->middleware(['permission:create injongepay_connect_platform.integrator', 'password.confirm.staff'])->name('integrators.store');
    Route::get('/integrators/{integrator}', [IntegratorController::class, 'show'])->middleware('permission:read injongepay_connect_platform.integrator')->name('integrators.show');
    Route::put('/integrators/{integrator}', [IntegratorController::class, 'update'])->middleware('permission:update injongepay_connect_platform.integrator')->name('integrators.update');
    Route::post('/integrators/{integrator}/toggle', [IntegratorController::class, 'toggle'])->middleware(['permission:update injongepay_connect_platform.integrator', 'password.confirm.staff'])->name('integrators.toggle');

    Route::get('/calls', [CallController::class, 'index'])->middleware('permission:list injongepay_connect_platform.call')->name('calls.index');
    Route::get('/calls/{call}', [CallController::class, 'show'])->middleware('permission:read injongepay_connect_platform.call')->name('calls.show');
});
