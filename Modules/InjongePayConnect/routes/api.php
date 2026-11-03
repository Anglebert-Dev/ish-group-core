<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePayConnect\Http\Controllers\Api\LegacyEbmController;
use Modules\InjongePayConnect\Http\Controllers\Api\V1\PartnerController;

// Injonge EBM/POS contract: same URLs as the production app.
Route::prefix('api/partner/v1')->name('injongepayconnect.v1.')->group(function () {
    Route::post('/oauth/token', [PartnerController::class, 'token'])->middleware('throttle:injongepay-partner-token')->name('token');

    Route::middleware(['partner.auth:pos-ebm-v1', 'throttle:injongepay-partner'])->group(function () {
        Route::get('/institution', [PartnerController::class, 'institution'])->name('institution');
        Route::get('/merchant-accounts', [PartnerController::class, 'merchantAccounts'])->name('merchant-accounts');
        Route::get('/merchant-accounts/{merchant_account}/payments', [PartnerController::class, 'payments'])->name('payments');
        Route::get('/merchant-accounts/{merchant_account}/totals', [PartnerController::class, 'totals'])->name('totals');
        Route::post('/payments/{payment}/confirm', [PartnerController::class, 'confirm'])->name('confirm');
        Route::post('/payments/{payment}/unconfirm', [PartnerController::class, 'unconfirm'])->name('unconfirm');
        Route::get('/payments/{payment}', [PartnerController::class, 'payment'])->name('payment');
        Route::get('/invoices/{invoice_no}/payments', [PartnerController::class, 'invoice'])->name('invoice');
    });
});

Route::get('api/ebm/payments/lookup', [LegacyEbmController::class, 'lookup'])->middleware('throttle:60,1')->name('injongepayconnect.legacy-ebm');
