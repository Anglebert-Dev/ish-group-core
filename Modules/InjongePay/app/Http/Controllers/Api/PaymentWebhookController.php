<?php

namespace Modules\InjongePay\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\WebhookInbox;
use Modules\InjongePay\Support\Money;
use Throwable;

/**
 * POST /api/payments/webhook: GTBank's transaction notification (v2.5 §10.1).
 * Same URL, fields, secret header and response bodies as before. Differences,
 * following GTBank's own §10.1.5: a redelivered transaction gets 200 with the
 * payment it already created, and money for a code that can't take it is
 * held (200) instead of refused.
 */
class PaymentWebhookController
{
    use RespondsLikeProduction;

    public function receive(Request $request, WebhookInbox $inbox): JsonResponse
    {
        $started = microtime(true);
        $log = fn (int $status, array $body, string $outcome, ?string $message = null, $subject = null) => GtbankClient::logInbound(
            '/api/payments/webhook', $request->all(), $status, $body, $outcome, $message, $request->ip(),
            $request->input('transactionId'), $subject, (int) ((microtime(true) - $started) * 1000),
        );

        if (! $this->secretMatches($request)) {
            $response = $this->sendUnauthorized();
            $log(401, $response->getData(true), BankResult::REJECTED, 'Missing or mismatched webhook secret');

            return $response;
        }

        $validator = Validator::make($request->all(), [
            'transactionId' => 'required|string|max:64',
            'merchantId' => 'required|string|max:20',
            'amount' => 'required|numeric|min:0.01',
            'valueDate' => 'nullable|date',
            'status' => 'nullable|string',
            'participantName' => 'nullable|string',
            'initiatingPartyName' => 'nullable|string',
            'initiatingPartyId' => 'nullable|string',
            'debtorName' => 'nullable|string',
            'debtorAccountId' => 'nullable|string',
            'creditorName' => 'nullable|string',
            'creditorAccountId' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $response = $this->sendError('Validation failed.', $validator->errors(), 422);
            $log(422, $response->getData(true), BankResult::REJECTED, 'Validation failed');

            return $response;
        }

        $data = $validator->validated();
        $status = strtoupper($data['status'] ?? 'SUCCESS');
        $event = $inbox->store('payment', $data['transactionId'].':'.$status, $request->all(), $request->ip());

        try {
            ['outcome' => $outcome, 'subject' => $subject] = $inbox->processPayment($event);
        } catch (InjongePayException $e) {
            $response = $this->sendError($e->getMessage(), null, 422);
            $log(422, $response->getData(true), BankResult::REJECTED, $e->getMessage());

            return $response;
        } catch (Throwable $e) {
            report($e);
            // Kept in the inbox: GTBank's retry or the scheduler processes it again.
            $response = $this->sendError('Could not process payment.', null, 500);
            $log(500, $response->getData(true), BankResult::UNKNOWN, $e->getMessage());

            return $response;
        }

        $response = match (true) {
            $outcome === 'ignored' => $this->sendSuccess(null, 'Acknowledged.', 200),
            $subject instanceof Payment => $this->sendSuccess([
                'reference' => $subject->reference,
                'wallet_merchant_code' => $subject->merchant_code,
                'gross_amount' => Money::toFloat($subject->gross_amount),
                'fee_amount' => Money::toFloat($subject->fee_amount),
                'net_amount' => Money::toFloat($subject->net_amount),
                'status' => $subject->payment_status,
            ], $outcome === PaymentIntake::CREATED ? 'Payment received.' : 'Payment already received.', $outcome === PaymentIntake::CREATED ? 201 : 200),
            default => $this->sendSuccess(null, 'Payment received and held for review.', 200),
        };

        $log($response->getStatusCode(), $response->getData(true), BankResult::OK, $outcome, $subject);

        return $response;
    }
}
