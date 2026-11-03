<?php

namespace Modules\InjongePay\Services;

use Modules\InjongePay\Support\Counter;

/**
 * GTBank merchant codes: '555' + 5 digits for businesses (55500001…), 900001+
 * for the platform's own wallets, which never go to GTBank.
 */
class MerchantCodes
{
    public function next(): string
    {
        $prefix = (string) config('injongepay.merchant_code_prefix', '555');
        $digits = (int) config('injongepay.merchant_code_digits', 5);

        return $prefix.str_pad((string) Counter::next('merchant_code'), $digits, '0', STR_PAD_LEFT);
    }

    public function nextInternal(): string
    {
        return (string) Counter::next('merchant_code:internal', (int) config('injongepay.internal_merchant_code_start', 900001) - 1);
    }

    /** GTBank's rule: starts with 55, 8 to 12 digits. */
    public static function isValidForBank(string $code): bool
    {
        return (bool) preg_match('/^55\d{6,10}$/', $code);
    }
}
