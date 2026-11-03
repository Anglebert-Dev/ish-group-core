<?php

namespace Modules\InjongePay\Gtbank;

/**
 * GTBank's answers don't always match its documentation (field names and
 * nesting vary). Every variant seen is read here, in one place.
 */
final class BankPayloads
{
    /**
     * /merchants/lookup: data.merchant{…} (doc) or data{merchantId, status} (inactive).
     *
     * @return array{merchant_id: ?string, status: ?string, name: ?string, owner: ?string}
     */
    public static function merchant(array $body): array
    {
        $data = $body['data'] ?? [];
        $merchant = is_array($data['merchant'] ?? null) ? $data['merchant'] : (is_array($data) ? $data : []);

        return [
            'merchant_id' => self::str($merchant['merchantId'] ?? null),
            'status' => ($status = self::str($merchant['status'] ?? null)) ? strtoupper($status) : null,
            'name' => self::str($merchant['businessName'] ?? null),
            'owner' => self::str($merchant['businessOwnerName'] ?? $merchant['businessOwner'] ?? null),
        ];
    }

    /**
     * /disbursement/status (snake_case in the doc) or the disbursement webhook (camelCase).
     *
     * @return array{status: ?string, reference: ?string, cbs_reference: ?string, amount: ?string, account: ?string, receiver: ?string, completed_at: ?string}
     */
    public static function disbursement(array $body): array
    {
        $status = self::str($body['status'] ?? null);

        return [
            'status' => $status ? strtoupper($status) : null,
            'reference' => self::str($body['request_id'] ?? $body['requestId'] ?? $body['identifier'] ?? $body['reference'] ?? null),
            'cbs_reference' => self::str($body['cbs_transaction_reference'] ?? $body['cbsTransactionReference'] ?? $body['document'] ?? null),
            'amount' => isset($body['amount']) ? (string) $body['amount'] : null,
            'account' => self::str($body['receiver_id'] ?? $body['beneficiaryAccount'] ?? $body['beneficiary']['account'] ?? null),
            'receiver' => self::str($body['receiver_names'] ?? $body['receiverNames'] ?? null),
            'completed_at' => self::str($body['completedAt'] ?? $body['created_at'] ?? $body['createdAt'] ?? null),
        ];
    }

    /**
     * /disbursement/lookup.
     *
     * @return array{names: ?string, account: ?string, bank_code: ?string, bank_name: ?string}
     */
    public static function account(array $body): array
    {
        return [
            'names' => self::str($body['names'] ?? $body['name'] ?? null),
            'account' => self::str($body['account'] ?? null),
            'bank_code' => self::str($body['bank']['code'] ?? null),
            'bank_name' => self::str($body['bank']['name'] ?? null),
        ];
    }

    /**
     * One row of /transactions or /participant/transactions, or the payment webhook.
     *
     * @return array{transaction_id: ?string, merchant_id: ?string, amount: ?string, status: string, value_date: ?string, channel: ?string, channel_id: ?string, payer_name: ?string, payer_account: ?string}
     */
    public static function transaction(array $row): array
    {
        return [
            'transaction_id' => self::str($row['transactionId'] ?? null),
            'merchant_id' => self::str($row['merchantId'] ?? $row['creditorAccountId'] ?? null),
            'amount' => isset($row['amount']) ? (string) $row['amount'] : null,
            'status' => strtoupper(self::str($row['status'] ?? null) ?? 'SUCCESS'),
            'value_date' => self::str($row['valueDate'] ?? null),
            'channel' => self::str($row['initiatingPartyName'] ?? null),
            'channel_id' => self::str($row['initiatingPartyId'] ?? null),
            'payer_name' => self::str($row['debtorName'] ?? null),
            'payer_account' => self::str($row['debtorAccountId'] ?? null),
        ];
    }

    /** Lists come back as a bare array, or wrapped in data. */
    public static function rows(array $body): array
    {
        if (array_is_list($body)) {
            return $body;
        }

        $data = $body['data'] ?? [];

        return is_array($data) && array_is_list($data) ? $data : ($data['transactions'] ?? []);
    }

    /** Errors are {"error": "…"} (doc) or {"message": "…"} (seen). */
    public static function error(array $body): ?string
    {
        return self::str($body['error'] ?? $body['message'] ?? $body['responseMessage'] ?? $body['error_description'] ?? null);
    }

    private static function str(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
