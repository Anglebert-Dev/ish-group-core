<?php

namespace Modules\InjongePay\Gtbank;

/**
 * GTBank PaymentBridge v2.5 §11.4 error codes → outcome.
 */
final class BankCodes
{
    public const MAP = [
        '00' => BankResult::OK,
        '01' => BankResult::NOT_FOUND,       // merchant not found
        '02' => BankResult::REJECTED,        // merchant already exists
        '03' => BankResult::REJECTED,        // invalid merchant details
        '04' => BankResult::REJECTED,        // unauthorized participant
        '05' => BankResult::REJECTED,        // invalid request parameters
        '06' => BankResult::REJECTED,        // merchant is closed
        '07' => BankResult::REJECTED,        // duplicate request
        '08' => BankResult::REJECTED,        // invalid token (refreshed and retried first)
        '09' => BankResult::REJECTED,        // expired token (refreshed and retried first)
        '10' => BankResult::REJECTED,        // insufficient funds in the participant settlement account
        '11' => BankResult::REJECTED,        // invalid merchant till code
        '12' => BankResult::UNKNOWN,         // payment processing timeout
        '13' => BankResult::REJECTED,        // reference already exists
        '14' => BankResult::REJECTED,        // invalid beneficiary account
        '15' => BankResult::REJECTED,        // disbursement processing failed
        '96' => BankResult::UNKNOWN,         // internal server error
    ];

    public const TOKEN_CODES = ['08', '09'];

    /** 0, "0", " 00 ", 1 → "00", "01": GTBank sends codes as numbers or strings. */
    public static function normalise(mixed $code): ?string
    {
        if ($code === null || $code === '' || ! is_scalar($code)) {
            return null;
        }

        $code = trim((string) $code);

        return ctype_digit($code) ? str_pad(ltrim($code, '0') ?: '0', 2, '0', STR_PAD_LEFT) : strtoupper($code);
    }

    public static function outcome(?string $code): string
    {
        return self::MAP[$code ?? ''] ?? BankResult::REJECTED;
    }
}
