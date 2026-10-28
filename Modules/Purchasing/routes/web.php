<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use Modules\Purchasing\Http\Controllers\SupplierController;

/*
|--------------------------------------------------------------------------
| Purchasing: /app/purchasing/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/purchasing')->name('purchasing.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->middleware('permission:list purchasing.supplier')->name('index');
            Route::get('/create', [SupplierController::class, 'create'])->middleware('permission:create purchasing.supplier')->name('create');
            Route::post('/', [SupplierController::class, 'store'])->middleware('permission:create purchasing.supplier')->name('store');
            Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('permission:read purchasing.supplier')->name('show');
            Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:update purchasing.supplier')->name('edit');
            Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:update purchasing.supplier')->name('update');
            Route::post('/{supplier}/payments', [SupplierController::class, 'pay'])->middleware('permission:create purchasing.payment')->name('pay');
            Route::post('/{supplier}/payments/{payment}/reverse', [SupplierController::class, 'reversePayment'])->middleware(['permission:delete purchasing.payment', 'password.confirm.staff'])->name('payments.reverse');
        });

        Route::get('/reorder', [PurchaseOrderController::class, 'reorder'])->middleware('permission:list purchasing.order')->name('reorder');

        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('permission:list purchasing.order')->name('index');
            Route::get('/create', [PurchaseOrderController::class, 'create'])->middleware('permission:create purchasing.order')->name('create');
            Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('permission:create purchasing.order')->name('store');
            Route::get('/{order}', [PurchaseOrderController::class, 'show'])->middleware('permission:read purchasing.order')->name('show');
            Route::get('/{order}/edit', [PurchaseOrderController::class, 'edit'])->middleware('permission:update purchasing.order')->name('edit');
            Route::put('/{order}', [PurchaseOrderController::class, 'update'])->middleware('permission:update purchasing.order')->name('update');
            Route::post('/{order}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:approve purchasing.order')->name('send');
            Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->middleware(['permission:create purchasing.receipt'])->name('receive');
            Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware(['permission:delete purchasing.order', 'password.confirm.staff'])->name('cancel');
        });
    });
