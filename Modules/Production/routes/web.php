<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\WorkOrderController;

/*
|--------------------------------------------------------------------------
| Production: /app/production/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/production')->name('production.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/settings', [WorkOrderController::class, 'settings'])->middleware('permission:update production.setting')->name('settings.edit');
        Route::put('/settings', [WorkOrderController::class, 'saveSettings'])->middleware('permission:update production.setting')->name('settings.update');

        Route::prefix('work-orders')->name('work-orders.')->group(function () {
            Route::get('/', [WorkOrderController::class, 'index'])->middleware('permission:list production.work_order')->name('index');
            Route::get('/create', [WorkOrderController::class, 'create'])->middleware('permission:create production.work_order')->name('create');
            Route::post('/', [WorkOrderController::class, 'store'])->middleware('permission:create production.work_order')->name('store');
            Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->middleware('permission:read production.work_order')->name('show');
            Route::get('/{workOrder}/edit', [WorkOrderController::class, 'edit'])->middleware('permission:update production.work_order')->name('edit');
            Route::put('/{workOrder}', [WorkOrderController::class, 'update'])->middleware('permission:update production.work_order')->name('update');
            Route::post('/{workOrder}/record', [WorkOrderController::class, 'record'])->middleware('permission:create production.entry')->name('record');
            Route::post('/{workOrder}/materials', [WorkOrderController::class, 'materials'])->middleware(['permission:create production.entry', 'permission:create inventory.movement'])->name('materials');
            Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete'])->middleware('permission:approve production.work_order')->name('complete');
            Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->middleware(['permission:delete production.work_order', 'password.confirm.staff'])->name('cancel');
        });
    });
