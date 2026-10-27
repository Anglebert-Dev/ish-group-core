<?php

use Illuminate\Support\Facades\Route;
use Modules\SalesOrders\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| Sales orders: /app/sales-orders/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/sales-orders')->name('salesorders.orders.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('permission:list sales.order')->name('index');
        Route::get('/export/pdf', [OrderController::class, 'exportPdf'])->middleware('permission:list sales.order')->name('export.pdf');
        Route::get('/export/excel', [OrderController::class, 'exportExcel'])->middleware('permission:list sales.order')->name('export.excel');
        Route::get('/create', [OrderController::class, 'create'])->middleware('permission:create sales.order')->name('create');
        Route::post('/', [OrderController::class, 'store'])->middleware('permission:create sales.order')->name('store');
        Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:read sales.order')->name('show');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->middleware('permission:update sales.order')->name('edit');
        Route::put('/{order}', [OrderController::class, 'update'])->middleware('permission:update sales.order')->name('update');
        Route::post('/{order}/confirm', [OrderController::class, 'confirm'])->middleware('permission:approve sales.order')->name('confirm');
        Route::put('/{order}/progress', [OrderController::class, 'progress'])->middleware('permission:update sales.order')->name('progress');
        Route::post('/{order}/deliver', [OrderController::class, 'deliver'])->middleware('permission:approve sales.order')->name('deliver');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->middleware(['permission:delete sales.order', 'password.confirm.staff'])->name('cancel');
    });
