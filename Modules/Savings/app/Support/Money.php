<?php

namespace Modules\Savings\Support;

use InvalidArgumentException;

/**
 * Money as whole minor units (RWF 25,600 → 25600; USD 12.50 → 1250).
 * Parsing and conversion use strings and bcmath, never floats.
 */
class Money
{
    public static function decimals(string $currency): int
    {
        $map = (array) Settings::get('decimals', config('savings.decimals', []));

        return (int) ($map[strtoupper($currency)] ?? config('savings.decimals.'.strtoupper($currency), 2));
    }

    public static function baseCurrency(): string
    {
        return strtoupper((string) Settings::get('currency', config('savings.currency', 'RWF')));
    }

    /**
     * "25,600" / "12.5" / "-3.75" → minor units. Rejects more decimals than the currency has.
     */
    public static function parse(string|int|null $value, string $currency): int
    {
        $value = str_replace([',', ' ', "\u{00A0}"], '', trim((string) $value));

        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('savings::savings.amount_invalid'));
        }

        $decimals = self::decimals($currency);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-')), 2, '');

        if (strlen(rtrim($fraction, '0')) > $decimals) {
            throw new InvalidArgumentException(__('savings::savings.amount_too_precise', ['decimals' => $decimals]));
        }

        $minor = (int) ($whole.str_pad(substr($fraction, 0, $decimals), $decimals, '0'));

        return $negative ? -$minor : $minor;
    }

    /**
     * Minor units → plain decimal string for inputs ("12.50", "25600").
     */
    public static function toDecimal(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);

        return $decimals === 0 ? (string) $minor : bcdiv((string) $minor, bcpow('10', (string) $decimals), $decimals);
    }

    /**
     * "RWF 25,600" / "USD 12.50" / "−RWF 3,000".
     */
    public static function format(int $minor, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?? self::baseCurrency());
        $decimals = self::decimals($currency);
        $abs = abs($minor);
        $whole = intdiv($abs, 10 ** $decimals);
        $fraction = $decimals > 0 ? '.'.str_pad((string) ($abs % (10 ** $decimals)), $decimals, '0', STR_PAD_LEFT) : '';

        return ($minor < 0 ? '−' : '').$currency.' '.number_format($whole).$fraction;
    }

    /**
     * Convert minor units between currencies at a decimal-string rate (1 from = rate to).
     */
    public static function convert(int $minor, string $from, string $to, string $rate): int
    {
        $shift = self::decimals($to) - self::decimals($from);
        $value = bcmul((string) $minor, $rate, 12);
        $value = $shift >= 0 ? bcmul($value, bcpow('10', (string) $shift), 12) : bcdiv($value, bcpow('10', (string) -$shift), 12);

        // Round half away from zero.
        $rounded = bcadd($value, str_starts_with($value, '-') ? '-0.5' : '0.5', 0);

        return (int) $rounded;
    }
}
