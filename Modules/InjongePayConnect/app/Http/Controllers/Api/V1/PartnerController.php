<?php

namespace Modules\InjongePayConnect\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Profiles\PosEbmV1;
use Modules\InjongePayConnect\Services\AlreadyConfirmed;
use Modules\InjongePayConnect\Services\Credentials;
use Modules\InjongePayConnect\Services\PartnerPayments;

/**
 * /api/partner/v1: the Injonge EBM/POS contract, with the same routes,
 * fields, codes and messages as the production app.
 */
class PartnerController
{
    public function __construct(protected PartnerPayments $payments)
    {
    }

    public function token(Request $request, Credentials $credentials): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grant_type' => 'required|in:client_credentials',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_request', $validator->errors()->first(), 400);
        }

        $result = $credentials->issue((string) $request->input('client_id'), (string) $request->input('client_secret'));

        if (! $result) {
            return $this->error('invalid_client', __('injongepayconnect::connect.invalid_client'), 401);
        }

        return response()->json(['token_type' => 'Bearer', 'access_token' => $result['access_token'], 'expires_in' => $result['expires_in']]);
    }

    public function institution(Request $request): JsonResponse
    {
        $connection = $this->connection($request);
        $business = $connection->business;

        return response()->json([
            'id' => $business->uuid,
            'name' => $business->name,
            'tin' => \Modules\InjongePay\Models\BusinessProfile::for($business)->tin,
            'status' => $business->is_active ? 'ACTIVE' : 'INACTIVE',
        ]);
    }

    public function merchantAccounts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payments->wallets($this->connection($request))->map(fn ($wallet) => [
            'id' => $wallet->uuid,
            'merchant_code' => $wallet->merchant_code,
            'name' => $wallet->name,
            'provider' => 'GTBANK',
            'status' => $wallet->bank_status === 'NOT_REGISTERED' || $wallet->bank_status === 'REGISTERING' ? 'PENDING' : $wallet->bank_status,
        ])->values()]);
    }

    public function totals(Request $request, string $merchant_account): JsonResponse
    {
        $wallet = $this->payments->wallet($this->connection($request), $merchant_account);

        if (! $wallet) {
            return $this->error('not_found', __('injongepayconnect::connect.wallet_not_found'), 404);
        }

        $validator = Validator::make($request->all(), ['date' => 'required|date_format:Y-m-d']);

        if ($validator->fails()) {
            return $this->error('validation_error', $validator->errors()->first(), 422);
        }

        return response()->json($this->payments->totals($wallet, (string) $request->input('date')));
    }

    public function payments(Request $request, string $merchant_account): JsonResponse
    {
        $wallet = $this->payments->wallet($this->connection($request), $merchant_account);

        if (! $wallet) {
            return $this->error('not_found', __('injongepayconnect::connect.wallet_not_found'), 404);
        }

        $page = $this->payments->list($wallet, $request->only(['status', 'amount', 'date_from', 'date_to', 'updated_since', 'search', 'page', 'per_page']));
        $active = $this->payments->activeFor($page->items());

        return response()->json([
            'data' => collect($page->items())->map(fn ($p) => PosEbmV1::payment($p, $active->get($p->id)))->values(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function payment(Request $request, string $payment): JsonResponse
    {
        $model = $this->payments->payment($this->connection($request), $payment);

        if (! $model) {
            return $this->error('not_found', __('injongepayconnect::connect.payment_not_found'), 404);
        }

        return response()->json(PosEbmV1::payment($model, $this->payments->active($model)));
    }

    public function confirm(Request $request, string $payment): JsonResponse
    {
        $connection = $this->connection($request);
        $model = $this->payments->payment($connection, $payment);

        if (! $model) {
            return $this->error('not_found', __('injongepayconnect::connect.payment_not_found'), 404);
        }

        $validator = Validator::make($request->all(), [
            'invoice_no' => 'required|string',
            'ebm_receipt_no' => 'nullable|string',
            'sdc_id' => 'nullable|string',
            'partial' => 'nullable|boolean',
            'note' => 'nullable|string|max:255',
            'invoice_total' => 'nullable|numeric|min:0',
            'balance_after' => 'nullable|numeric|min:0',
            'cashier.id' => 'nullable|string',
            'cashier.name' => 'nullable|string',
            'confirmed_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('validation_error', $validator->errors()->first(), 422);
        }

        try {
            $confirmation = $this->payments->confirm($connection, $model, $validator->validated() + ['idempotency_key' => $request->header('Idempotency-Key')]);
        } catch (AlreadyConfirmed $e) {
            return response()->json(['error' => ['code' => 'ALREADY_CONFIRMED', 'message' => $e->getMessage(), 'invoice_no' => $e->invoiceNo]], 409);
        } catch (InjongePayException $e) {
            return $this->error('conflict', $e->getMessage(), 409);
        }

        return response()->json(PosEbmV1::payment($model->fresh(), $confirmation));
    }

    public function unconfirm(Request $request, string $payment): JsonResponse
    {
        $connection = $this->connection($request);
        $model = $this->payments->payment($connection, $payment);

        if (! $model) {
            return $this->error('not_found', __('injongepayconnect::connect.payment_not_found'), 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
            'manager.id' => 'nullable|string',
            'manager.name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('validation_error', $validator->errors()->first(), 422);
        }

        try {
            $this->payments->unconfirm($connection, $model, $validator->validated());
        } catch (InjongePayException $e) {
            return $this->error('conflict', $e->getMessage(), 409);
        }

        return response()->json(PosEbmV1::payment($model->fresh(), null));
    }

    public function invoice(Request $request, string $invoice_no): JsonResponse
    {
        return response()->json(['data' => $this->payments->byInvoice($this->connection($request), $invoice_no)->map(fn ($c) => PosEbmV1::confirmation($c))->values()]);
    }

    private function connection(Request $request): Connection
    {
        return $request->attributes->get('partner_connection');
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
