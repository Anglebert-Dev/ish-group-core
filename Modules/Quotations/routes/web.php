<?php

use Illuminate\Support\Facades\Route;
use Modules\Quotations\Http\Controllers\QuotationController;

/*
|--------------------------------------------------------------------------
| Quotations: /app/quotations/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/quotations')->name('quotations.quotes.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->middleware('permission:list quotations.quote')->name('index');
        Route::get('/create', [QuotationController::class, 'create'])->middleware('permission:create quotations.quote')->name('create');
        Route::post('/', [QuotationController::class, 'store'])->middleware('permission:create quotations.quote')->name('store');
        Route::get('/{quote}', [QuotationController::class, 'show'])->middleware('permission:read quotations.quote')->name('show');
        Route::get('/{quote}/edit', [QuotationController::class, 'edit'])->middleware('permission:update quotations.quote')->name('edit');
        Route::put('/{quote}', [QuotationController::class, 'update'])->middleware('permission:update quotations.quote')->name('update');
        Route::put('/{quote}/status', [QuotationController::class, 'status'])->middleware('permission:update quotations.quote')->name('status');
        Route::put('/{quote}/sample', [QuotationController::class, 'sample'])->middleware('permission:update quotations.quote')->name('sample');
        Route::post('/{quote}/convert', [QuotationController::class, 'convert'])->middleware(['permission:approve quotations.quote', 'permission:create sales.order'])->name('convert');
        Route::post('/{quote}/deposits', [QuotationController::class, 'deposit'])->middleware('permission:create quotations.deposit')->name('deposits.store');
        Route::post('/{quote}/deposits/{deposit}/reverse', [QuotationController::class, 'reverseDeposit'])->middleware(['permission:delete quotations.deposit', 'password.confirm.staff'])->name('deposits.reverse');
    });
