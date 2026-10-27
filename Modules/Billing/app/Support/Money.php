<?php

namespace Modules\Billing\Support;

/**
 * All billing maths in integer cents; decimals only at the edges.
 */
class Money
{
    public static function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Cents → "1234.50" for decimal columns. */
    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Normalise any amount to "1234.50". */
    public static function format2(mixed $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }

    /** Display: "RWF 12,000" / "USD 12.50". */
    public static function format(mixed $amount, ?string $currency = null): string
    {
        $currency ??= BillingSettings::get('currency');
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) == 0.0 ? 0 : 2;

        return trim($currency.' '.number_format($value, $decimals));
    }
}
