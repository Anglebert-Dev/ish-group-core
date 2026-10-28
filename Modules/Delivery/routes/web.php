<?php

use Illuminate\Support\Facades\Route;
use Modules\Delivery\Http\Controllers\DeliveryController;

/*
|--------------------------------------------------------------------------
| Delivery: /app/deliveries/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/deliveries')->name('delivery.notes.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->middleware('permission:list delivery.note')->name('index');
        Route::get('/order/{order}', [DeliveryController::class, 'create'])->middleware('permission:create delivery.note')->name('create');
        Route::post('/order/{order}', [DeliveryController::class, 'store'])->middleware('permission:create delivery.note')->name('store');
        Route::get('/{note}', [DeliveryController::class, 'show'])->middleware('permission:read delivery.note')->name('show');
        Route::get('/{note}/pdf', [DeliveryController::class, 'pdf'])->middleware('permission:read delivery.note')->name('pdf');
    });
