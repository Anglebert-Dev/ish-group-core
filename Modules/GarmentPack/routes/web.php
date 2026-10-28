<?php

use Illuminate\Support\Facades\Route;
use Modules\GarmentPack\Http\Controllers\StyleController;
use Modules\GarmentPack\Http\Controllers\WorkshopController;

/*
|--------------------------------------------------------------------------
| Garment pack: /app/garments/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/garments')->name('garmentpack.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::prefix('styles')->name('styles.')->group(function () {
            Route::get('/', [StyleController::class, 'index'])->middleware('permission:list garments.style')->name('index');
            Route::get('/create', [StyleController::class, 'create'])->middleware('permission:create garments.style')->name('create');
            Route::post('/', [StyleController::class, 'store'])->middleware('permission:create garments.style')->name('store');
            Route::get('/{style}', [StyleController::class, 'show'])->middleware('permission:read garments.style')->name('show');
            Route::get('/{style}/edit', [StyleController::class, 'edit'])->middleware('permission:update garments.style')->name('edit');
            Route::put('/{style}', [StyleController::class, 'update'])->middleware('permission:update garments.style')->name('update');
        });

        Route::get('/workers', [WorkshopController::class, 'workers'])->middleware('permission:list garments.worker')->name('workers.index');
        Route::post('/workers', [WorkshopController::class, 'storeWorker'])->middleware('permission:create garments.worker')->name('workers.store');
        Route::put('/workers/{worker}', [WorkshopController::class, 'updateWorker'])->middleware('permission:update garments.worker')->name('workers.update');
        Route::post('/workers/{worker}/paid', [WorkshopController::class, 'markPaid'])->middleware(['permission:approve garments.worker', 'password.confirm.staff'])->name('workers.paid');

        Route::get('/cmt', [WorkshopController::class, 'cmtIndex'])->middleware('permission:list garments.cmt')->name('cmt.index');
        Route::post('/cmt/{job}/return', [WorkshopController::class, 'cmtReturn'])->middleware(['permission:update garments.cmt', 'permission:create production.entry'])->name('cmt.return');

        Route::post('/work-orders/{workOrder}/piece-work', [WorkshopController::class, 'pieceWork'])->middleware('permission:create production.entry')->name('work-orders.piece-work');
        Route::post('/work-orders/{workOrder}/cmt', [WorkshopController::class, 'cmtSend'])->middleware('permission:create garments.cmt')->name('work-orders.cmt');
        Route::post('/work-orders/{workOrder}/bundles', [WorkshopController::class, 'bundles'])->middleware('permission:create production.entry')->name('work-orders.bundles');
        Route::get('/work-orders/{workOrder}/tickets', [WorkshopController::class, 'tickets'])->middleware('permission:read production.work_order')->name('work-orders.tickets');

        Route::post('/customers/{customer}/measurements', [WorkshopController::class, 'storeMeasurement'])->middleware('permission:update customers.customer')->name('measurements.store');
        Route::delete('/measurements/{measurement}', [WorkshopController::class, 'destroyMeasurement'])->middleware('permission:update customers.customer')->name('measurements.destroy');

        Route::post('/work-orders/{workOrder}/issue-materials', [StyleController::class, 'issue'])
            ->middleware(['permission:create production.entry', 'permission:create inventory.movement'])->name('work-orders.issue');
    });
