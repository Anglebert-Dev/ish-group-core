<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Admin\CouponController;
use Modules\Billing\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use Modules\Billing\Http\Controllers\Admin\OverviewController;
use Modules\Billing\Http\Controllers\Admin\PackageController;
use Modules\Billing\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use Modules\Billing\Http\Controllers\Admin\SettingsController;
use Modules\Billing\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use Modules\Billing\Http\Controllers\BillingController;

/*
|--------------------------------------------------------------------------
| Billing module
|--------------------------------------------------------------------------
| Loaded with ['web', 'module:Billing'].
|
| /app/billing                 the signed-in business: plan, usage, invoices, pay
| /app/super-admin/billing     platform: packages, subscriptions, invoices,
|                              payments to approve, coupons, settings
*/

Route::get('/pricing', [\Modules\Billing\Http\Controllers\PricingController::class, 'index'])->name('pricing');

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'];

Route::prefix('app/billing')->name('billing.')->middleware($staff)->group(function () {
    Route::get('/', [BillingController::class, 'index'])->middleware('permission:list billing.subscription')->name('index');
    Route::get('/plans', [BillingController::class, 'plans'])->middleware('permission:manage billing.subscription')->name('plans');
    Route::get('/plans/{package}', [BillingController::class, 'checkout'])->middleware('permission:manage billing.subscription')->name('checkout');
    Route::post('/plans/{package}', [BillingController::class, 'subscribe'])->middleware(['permission:manage billing.subscription', 'password.confirm.staff'])->name('subscribe');
    Route::post('/cancel', [BillingController::class, 'cancel'])->middleware(['permission:manage billing.subscription', 'password.confirm.staff'])->name('cancel');
    Route::post('/resume', [BillingController::class, 'resume'])->middleware('permission:manage billing.subscription')->name('resume');
    Route::put('/account', [BillingController::class, 'updateAccount'])->middleware('permission:manage billing.subscription')->name('account');

    Route::get('/invoices/{invoice}', [BillingController::class, 'invoice'])->middleware('permission:read billing.invoice')->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [BillingController::class, 'invoicePdf'])->middleware('permission:read billing.invoice')->name('invoices.pdf');
    Route::post('/invoices/{invoice}/payments', [BillingController::class, 'submitPayment'])->middleware('permission:create billing.payment')->name('invoices.pay');
});

Route::prefix('app/super-admin/billing')->name('superadmin.billing.')->middleware([...$staff, 'superadmin'])->group(function () {
    Route::get('/', [OverviewController::class, 'index'])->name('overview');

    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
    Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->middleware('password.confirm.staff')->name('packages.destroy');

    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/export/{format}', [AdminSubscriptionController::class, 'export'])->whereIn('format', ['pdf', 'excel'])->name('subscriptions.export');
    Route::get('/subscriptions/{subscription}', [AdminSubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::post('/businesses/{business}/subscribe', [AdminSubscriptionController::class, 'assign'])->middleware('password.confirm.staff')->name('subscriptions.assign');
    Route::put('/subscriptions/{subscription}/extend', [AdminSubscriptionController::class, 'extend'])->middleware('password.confirm.staff')->name('subscriptions.extend');
    Route::post('/subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel'])->middleware('password.confirm.staff')->name('subscriptions.cancel');

    Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/export/{format}', [AdminInvoiceController::class, 'export'])->whereIn('format', ['pdf', 'excel'])->name('invoices.export');
    Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [AdminInvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->middleware('password.confirm.staff')->name('invoices.void');
    Route::post('/invoices/{invoice}/payments', [AdminInvoiceController::class, 'recordPayment'])->middleware('password.confirm.staff')->name('invoices.record-payment');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/export/{format}', [AdminPaymentController::class, 'export'])->whereIn('format', ['pdf', 'excel'])->name('payments.export');
    Route::get('/payments/{payment}/proof', [AdminPaymentController::class, 'proof'])->name('payments.proof');
    Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->middleware('password.confirm.staff')->name('payments.approve');
    Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->middleware('password.confirm.staff')->name('payments.reject');

    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
