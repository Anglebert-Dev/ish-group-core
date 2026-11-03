<?php

use Illuminate\Support\Facades\Route;
use Modules\InjongePay\Http\Controllers\Api\PaymentWebhookController;

Route::post('/payments/webhook', [PaymentWebhookController::class, 'receive'])->name('injongepay.webhook.payment');
