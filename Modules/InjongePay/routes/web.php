<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePay\Http\Controllers\Business\OverviewController;
use Modules\InjongePay\Http\Controllers\Business\ApprovalController;
use Modules\InjongePay\Http\Controllers\Business\CashoutController;
use Modules\InjongePay\Http\Controllers\Platform\CashoutController as PlatformCashoutController;
use Modules\InjongePay\Http\Controllers\Business\PaymentController;
use Modules\InjongePay\Http\Controllers\Business\ProfileController;
use Modules\InjongePay\Http\Controllers\Business\ReportController;
use Modules\InjongePay\Http\Controllers\Platform\EarningsController;
use Modules\InjongePay\Http\Controllers\Platform\FinanceController;
use Modules\InjongePay\Http\Controllers\Business\SecurityController;
use Modules\InjongePay\Http\Controllers\Business\SettlementAccountController as BusinessSettlementAccountController;
use Modules\InjongePay\Http\Controllers\Business\TransferController;
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
    Route::get('/wallets/{wallet}', [OverviewController::class, 'wallet'])->middleware('permission:read injongepay.wallet')->name('wallets.show');
    Route::get('/business', [ProfileController::class, 'edit'])->middleware('permission:read injongepay.profile')->name('profile');
    Route::put('/business', [ProfileController::class, 'update'])->middleware('permission:update injongepay.profile')->name('profile.update');
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:list injongepay.payment')->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:read injongepay.payment')->name('payments.show');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:list injongepay.report')->name('reports');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->middleware(['permission:list injongepay.report', 'throttle:10,1'])->whereIn('format', ['pdf', 'excel'])->name('reports.export');
    Route::get('/wallets/{wallet}/ledger', [ReportController::class, 'ledger'])->middleware('permission:list injongepay.ledger')->name('ledger');

    Route::get('/transfers', [TransferController::class, 'index'])->middleware('permission:list injongepay.transfer')->name('transfers.index');
    Route::post('/transfers', [TransferController::class, 'store'])->middleware(['permission:create injongepay.transfer', 'not.impersonating', 'throttle:20,1'])->name('transfers.store');

    Route::get('/cashouts', [CashoutController::class, 'index'])->middleware('permission:list injongepay.cashout')->name('cashouts.index');
    Route::post('/cashouts', [CashoutController::class, 'store'])->middleware(['permission:create injongepay.cashout', 'not.impersonating', 'throttle:10,1'])->name('cashouts.store');
    Route::get('/cashouts/{cashout}', [CashoutController::class, 'show'])->middleware('permission:read injongepay.cashout')->name('cashouts.show');

    Route::get('/settlement-accounts', [BusinessSettlementAccountController::class, 'index'])->middleware('permission:list injongepay.settlement_account')->name('settlement-accounts.index');
    Route::post('/settlement-accounts', [BusinessSettlementAccountController::class, 'store'])->middleware(['permission:create injongepay.settlement_account', 'not.impersonating', 'throttle:10,1'])->name('settlement-accounts.store');
    Route::post('/settlement-accounts/{account}/verify', [BusinessSettlementAccountController::class, 'verify'])->middleware(['permission:update injongepay.settlement_account', 'throttle:10,1'])->name('settlement-accounts.verify');
    Route::delete('/settlement-accounts/{account}', [BusinessSettlementAccountController::class, 'destroy'])->middleware(['permission:delete injongepay.settlement_account', 'not.impersonating'])->name('settlement-accounts.destroy');

    Route::get('/approvals', [ApprovalController::class, 'index'])->middleware('permission:approve injongepay.transfer|approve injongepay.cashout|create injongepay.transfer|create injongepay.cashout')->name('approvals.index');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->middleware(['not.impersonating', 'throttle:20,1'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->middleware('throttle:20,1')->name('approvals.reject');

    Route::get('/security', [SecurityController::class, 'show'])->middleware('permission:create injongepay.transfer|create injongepay.cashout|approve injongepay.transfer|approve injongepay.cashout')->name('security');
    Route::post('/security/pin', [SecurityController::class, 'setPin'])->middleware(['password.confirm.staff', 'not.impersonating', 'throttle:6,1'])->name('security.pin');
    Route::delete('/security/devices/{device}', [SecurityController::class, 'revoke'])->name('security.devices.revoke');
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


    Route::get('/earnings', [EarningsController::class, 'index'])->middleware('permission:list injongepay_platform.earnings')->name('earnings');
    Route::post('/earnings/accounts', [EarningsController::class, 'addAccount'])->middleware(['permission:create injongepay_platform.earnings', 'not.impersonating', 'throttle:10,1'])->name('earnings.accounts.store');
    Route::delete('/earnings/accounts/{account}', [EarningsController::class, 'removeAccount'])->middleware(['permission:delete injongepay_platform.earnings', 'not.impersonating'])->name('earnings.accounts.destroy');
    Route::post('/earnings/cashout', [EarningsController::class, 'cashout'])->middleware(['permission:create injongepay_platform.earnings', 'not.impersonating', 'throttle:10,1'])->name('earnings.cashout');

    Route::get('/finance', [FinanceController::class, 'index'])->middleware('permission:list injongepay_platform.reconciliation')->name('finance');
    Route::get('/finance/export', [FinanceController::class, 'export'])->middleware(['permission:list injongepay_platform.reconciliation', 'throttle:10,1'])->name('finance.export');

    Route::get('/cashouts', [PlatformCashoutController::class, 'index'])->middleware('permission:list injongepay_platform.cashout')->name('cashouts.index');
    Route::get('/cashouts/{cashout}', [PlatformCashoutController::class, 'show'])->middleware('permission:read injongepay_platform.cashout')->name('cashouts.show');
    Route::post('/cashouts/{cashout}/check', [PlatformCashoutController::class, 'check'])->middleware(['permission:update injongepay_platform.cashout', 'throttle:20,1'])->name('cashouts.check');
    Route::post('/cashouts/{cashout}/resolve', [PlatformCashoutController::class, 'resolve'])->middleware(['permission:approve injongepay_platform.cashout', 'password.confirm.staff'])->name('cashouts.resolve');

    Route::get('/bank-calls', [BankCallController::class, 'index'])->middleware('permission:list injongepay_platform.bank')->name('bank-calls.index');
    Route::get('/bank-calls/{call}', [BankCallController::class, 'show'])->middleware('permission:read injongepay_platform.bank')->name('bank-calls.show');

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:update injongepay_platform.setting|update injongepay_platform.fee')->name('settings');
    Route::put('/settings/fees', [SettingsController::class, 'updateFees'])->middleware(['permission:update injongepay_platform.fee', 'password.confirm.staff'])->name('settings.fees');
    Route::put('/settings/webhook-ips', [SettingsController::class, 'updateIps'])->middleware(['permission:update injongepay_platform.setting', 'password.confirm.staff'])->name('settings.ips');
    Route::post('/settings/webhook-secret', [SettingsController::class, 'generateSecret'])->middleware(['permission:update injongepay_platform.setting', 'password.confirm.staff'])->name('settings.secret');
});
