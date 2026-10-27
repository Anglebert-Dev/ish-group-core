<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoicing\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Invoicing: /app/invoices/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/invoices')->name('invoicing.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/settings', [InvoiceController::class, 'settings'])->middleware('permission:update invoicing.setting')->name('settings.edit');
        Route::put('/settings', [InvoiceController::class, 'saveSettings'])->middleware('permission:update invoicing.setting')->name('settings.update');

        Route::name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:list invoicing.invoice')->name('index');
            Route::get('/export/pdf', [InvoiceController::class, 'exportPdf'])->middleware('permission:list invoicing.invoice')->name('export.pdf');
            Route::get('/export/excel', [InvoiceController::class, 'exportExcel'])->middleware('permission:list invoicing.invoice')->name('export.excel');
            Route::get('/create', [InvoiceController::class, 'create'])->middleware('permission:create invoicing.invoice')->name('create');
            Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:create invoicing.invoice')->name('store');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:read invoicing.invoice')->name('show');
            Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:read invoicing.invoice')->name('pdf');
            Route::post('/{invoice}/payments', [InvoiceController::class, 'pay'])->middleware('permission:create invoicing.payment')->name('pay');
            Route::post('/{invoice}/payments/{payment}/reverse', [InvoiceController::class, 'reversePayment'])->middleware(['permission:delete invoicing.payment', 'password.confirm.staff'])->name('payments.reverse');
            Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware(['permission:delete invoicing.invoice', 'password.confirm.staff'])->name('cancel');
        });
    });
