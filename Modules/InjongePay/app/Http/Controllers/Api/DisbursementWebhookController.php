<?php

namespace Modules\InjongePay\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\CashoutService;
use Modules\InjongePay\Services\WebhookInbox;
use Throwable;

/**
 * POST /api/gtbank/disbursement-status (v2.5 §10.1.4). Same URL, fields and
 * answers as before. The webhook alone never settles a cash-out: it is one
 * signal, and a status read must agree.
 */
class DisbursementWebhookController
{
    use RespondsLikeProduction;

    public function receive(Request $request, WebhookInbox $inbox, CashoutService $cashouts): JsonResponse
    {
        $log = fn (int $status, array $body, string $outcome, ?string $message = null, $subject = null) => GtbankClient::logInbound(
            '/api/gtbank/disbursement-status', $request->all(), $status, $body, $outcome, $message, $request->ip(), $request->input('identifier'), $subject,
        );

        if (! $this->fromGtbank($request)) {
            $response = $this->sendUnauthorized();
            $log(401, $response->getData(true), BankResult::REJECTED, 'Not from GTBank');

            return $response;
        }

        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:64',
            'status' => 'required|string|max:20',
            'amount' => 'nullable|numeric',
            'currency' => 'nullable|string|max:3',
            'beneficiaryAccount' => 'nullable|string',
            'receiverNames' => 'nullable|string',
            'creditTime' => 'nullable|string',
            'completedAt' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $response = $this->sendError('Validation failed.', $validator->errors(), 422);
            $log(422, $response->getData(true), BankResult::REJECTED, 'Validation failed');

            return $response;
        }

        $data = $validator->validated();
        $event = $inbox->store('disbursement', $data['identifier'].':'.strtoupper($data['status']), $request->all(), $request->ip());
        $cashout = Cashout::query()->withoutGlobalScopes()->where('reference', $data['identifier'])->orWhere('bank_reference', $data['identifier'])->first();

        try {
            if ($cashout) {
                $cashouts->webhook($cashout, $request->all());
            }
            $event->forceFill(['processed_at' => now(), 'outcome' => $cashout ? 'applied' : 'ignored', 'subject_type' => $cashout?->getMorphClass(), 'subject_id' => $cashout?->id])->save();
        } catch (Throwable $e) {
            report($e);
            $event->forceFill(['attempts' => $event->attempts + 1, 'last_error' => $e->getMessage(), 'outcome' => 'failed'])->save();
            $response = $this->sendError('Could not process disbursement status.', null, 500);
            $log(500, $response->getData(true), BankResult::UNKNOWN, $e->getMessage(), $cashout);

            return $response;
        }

        $response = $this->sendSuccess(null, 'Acknowledged.', 200);
        $log(200, $response->getData(true), BankResult::OK, $cashout ? null : 'Unknown identifier', $cashout);

        return $response;
    }
}
