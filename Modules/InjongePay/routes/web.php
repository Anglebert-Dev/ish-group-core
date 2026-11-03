<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePay\Http\Controllers\Business\OverviewController;
use Modules\InjongePay\Http\Controllers\Business\PaymentController;
use Modules\InjongePay\Http\Controllers\Platform\ReconciliationController;
use Modules\InjongePay\Http\Controllers\Platform\UnallocatedController;
use Modules\InjongePay\Http\Controllers\Platform\BankCallController;
use Modules\InjongePay\Http\Controllers\Platform\BusinessController;
use Modules\InjongePay\Http\Controllers\Platform\WalletController;
use Modules\InjongePay\Http\Controllers\Platform\SettingsController;

/*
|--------------------------------------------------------------------------
| Injonge Pay: /app/injonge-pay/... (a business's own panel) and
| /app/injonge-pay/platform/... (the operator, across businesses)
|--------------------------------------------------------------------------
*/

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'];

Route::prefix('app/injonge-pay')->name('injongepay.')->middleware($staff)->group(function () {
    Route::get('/', [OverviewController::class, 'index'])->middleware('permission:list injongepay.overview')->name('overview');
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:list injongepay.payment')->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:read injongepay.payment')->name('payments.show');
});

Route::prefix('app/injonge-pay/platform')->name('injongepay.platform.')->middleware($staff)->group(function () {
    Route::get('/businesses', [BusinessController::class, 'index'])->middleware('permission:list injongepay_platform.business')->name('businesses.index');
    Route::get('/businesses/{business}', [BusinessController::class, 'edit'])->middleware('permission:read injongepay_platform.business')->name('businesses.edit');
    Route::put('/businesses/{business}', [BusinessController::class, 'update'])->middleware(['permission:update injongepay_platform.business', 'password.confirm.staff'])->name('businesses.update');

    Route::get('/wallets', [WalletController::class, 'index'])->middleware('permission:list injongepay_platform.wallet')->name('wallets.index');
    Route::get('/wallets/create', [WalletController::class, 'create'])->middleware('permission:create injongepay_platform.wallet')->name('wallets.create');
    Route::post('/wallets', [WalletController::class, 'store'])->middleware(['permission:create injongepay_platform.wallet', 'password.confirm.staff'])->name('wallets.store');
    Route::post('/wallets/sync-waiting', [WalletController::class, 'syncWaiting'])->middleware(['permission:update injongepay_platform.bank', 'throttle:6,1'])->name('wallets.sync-waiting');
    Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->middleware('permission:read injongepay_platform.wallet')->name('wallets.show');
    Route::put('/wallets/{wallet}', [WalletController::class, 'update'])->middleware(['permission:update injongepay_platform.wallet', 'password.confirm.staff'])->name('wallets.update');
    Route::post('/wallets/{wallet}/register', [WalletController::class, 'register'])->middleware(['permission:update injongepay_platform.bank', 'throttle:10,1'])->name('wallets.register');
    Route::post('/wallets/{wallet}/sync', [WalletController::class, 'sync'])->middleware(['permission:update injongepay_platform.bank', 'throttle:20,1'])->name('wallets.sync');
    Route::put('/wallets/{wallet}/toggle', [WalletController::class, 'toggle'])->middleware(['permission:update injongepay_platform.wallet', 'password.confirm.staff'])->name('wallets.toggle');
    Route::post('/wallets/{wallet}/close', [WalletController::class, 'close'])->middleware(['permission:delete injongepay_platform.wallet', 'password.confirm.staff'])->name('wallets.close');

    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->middleware('permission:list injongepay_platform.reconciliation')->name('reconciliation.index');
    Route::post('/reconciliation/pull', [ReconciliationController::class, 'pull'])->middleware(['permission:update injongepay_platform.bank', 'throttle:6,1'])->name('reconciliation.pull');
    Route::post('/reconciliation/close-day', [ReconciliationController::class, 'closeDay'])->middleware(['permission:update injongepay_platform.reconciliation', 'throttle:6,1'])->name('reconciliation.close-day');
    Route::post('/reconciliation/{item}/resolve', [ReconciliationController::class, 'resolve'])->middleware(['permission:approve injongepay_platform.reconciliation', 'password.confirm.staff'])->name('reconciliation.resolve');
    Route::get('/unallocated', [UnallocatedController::class, 'index'])->middleware('permission:list injongepay_platform.reconciliation')->name('unallocated.index');
    Route::post('/unallocated/{row}/allocate', [UnallocatedController::class, 'allocate'])->middleware(['permission:approve injongepay_platform.reconciliation', 'password.confirm.staff'])->name('unallocated.allocate');
    Route::post('/unallocated/{row}/settle-outside', [UnallocatedController::class, 'settleOutside'])->middleware(['permission:approve injongepay_platform.reconciliation', 'password.confirm.staff'])->name('unallocated.settle-outside');
    Route::post('/inbox/process', [UnallocatedController::class, 'processInbox'])->middleware(['permission:update injongepay_platform.bank', 'throttle:6,1'])->name('inbox.process');
    Route::post('/fees/sweep', [UnallocatedController::class, 'sweepFees'])->middleware(['permission:update injongepay_platform.fee', 'throttle:6,1'])->name('fees.sweep');

    Route::get('/bank-calls', [BankCallController::class, 'index'])->middleware('permission:list injongepay_platform.bank')->name('bank-calls.index');
    Route::get('/bank-calls/{call}', [BankCallController::class, 'show'])->middleware('permission:read injongepay_platform.bank')->name('bank-calls.show');

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:update injongepay_platform.setting|update injongepay_platform.fee')->name('settings');
    Route::put('/settings/fees', [SettingsController::class, 'updateFees'])->middleware(['permission:update injongepay_platform.fee', 'password.confirm.staff'])->name('settings.fees');
    Route::put('/settings/webhook-ips', [SettingsController::class, 'updateIps'])->middleware(['permission:update injongepay_platform.setting', 'password.confirm.staff'])->name('settings.ips');
    Route::post('/settings/webhook-secret', [SettingsController::class, 'generateSecret'])->middleware(['permission:update injongepay_platform.setting', 'password.confirm.staff'])->name('settings.secret');
});
