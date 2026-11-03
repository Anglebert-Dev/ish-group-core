<?php

namespace Modules\InjongePayConnect\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Support\Money;

/**
 * GET /api/ebm/payments/lookup: the old single-key lookup, unchanged. It sees
 * every business's payments, so give the key only to whoever already has it;
 * new integrations use /api/partner/v1.
 */
class LegacyEbmController
{
    public function lookup(Request $request): JsonResponse
    {
        $expected = (string) config('injongepayconnect.legacy_ebm_key');

        if ($expected === '' || ! hash_equals($expected, (string) $request->header('X-EBM-Api-Key'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized', 'errors' => null], 401);
        }

        $validator = Validator::make($request->all(), [
            'reference' => 'required_without:bank_reference|nullable|string',
            'bank_reference' => 'required_without:reference|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $payment = Payment::query()->withoutGlobalScopes()
            ->when($request->filled('reference'), fn ($q) => $q->where('reference', $request->input('reference')), fn ($q) => $q->where('bank_reference', $request->input('bank_reference')))
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'No payment found for the given reference.', 'errors' => null], 404);
        }

        audit('injongepay_legacy_lookup', 'Legacy EBM lookup: '.$payment->reference, $payment, ['ip' => $request->ip()], $payment->business_id);

        return response()->json(['success' => true, 'message' => 'Payment found.', 'data' => [
            'reference' => $payment->reference,
            'bank_reference' => $payment->bank_reference,
            'merchant_code' => $payment->merchant_code,
            'buyer_name' => $payment->buyer_name,
            'buyer_phone' => $payment->buyer_phone,
            'gross_amount' => Money::toFloat($payment->gross_amount),
            'fee_amount' => Money::toFloat($payment->fee_amount),
            'net_amount' => Money::toFloat($payment->net_amount),
            'payment_status' => $payment->payment_status,
            'bank_status' => $payment->payment_status === Payment::STATUS_REVERSED ? 'REVERSED' : 'SUCCESS',
            'paid_at' => \Modules\InjongePayConnect\Profiles\PosEbmV1::time($payment->created_at),
        ]]);
    }
}
