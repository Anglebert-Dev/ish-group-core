<?php

namespace Modules\InjongePay\Support;

use InvalidArgumentException;

/**
 * RWF amounts as whole cents (int). Production stores 2 decimals, so cents keep
 * every imported amount exact; rates are decimal strings, maths is bcmath.
 */
final class Money
{
    public const CURRENCY = 'RWF';

    /** "9800.50", 9800.5 or 9800 → 980050. Refuses more than 2 decimals. */
    public static function toCents(string|int|float $amount): int
    {
        $value = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);
        $value = str_replace([',', ' '], '', $value);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Not an amount: {$value}");
        }

        return (int) bcmul($value, '100', 0);
    }

    /** 980050 → "9800.50" */
    public static function toDecimal(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    /** For JSON responses that must stay as they were (floats with 2 decimals). */
    public static function toFloat(int $cents): float
    {
        return (float) self::toDecimal($cents);
    }

    public static function format(int $cents): string
    {
        return money(self::toDecimal($cents), self::CURRENCY);
    }

    /** $cents × $percent / 100, rounded half up to the cent (what production did with round(x, 2)). */
    public static function percentOf(int $cents, string $percent): int
    {
        $raw = bcdiv(bcmul((string) $cents, $percent, 6), '100', 6);

        return (int) bcadd($raw, bccomp($raw, '0', 6) >= 0 ? '0.5' : '-0.5', 0);
    }

    public static function isWholeUnits(int $cents): bool
    {
        return $cents % 100 === 0;
    }
}
