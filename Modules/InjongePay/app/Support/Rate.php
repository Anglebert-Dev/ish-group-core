<?php

namespace Modules\InjongePay\Support;

/**
 * Percentages (fee, bank cut) with up to 4 decimals, e.g. 0.025%. Kept as
 * strings for bcmath; shown without trailing zeros.
 */
final class Rate
{
    public const SCALE = 4;

    public static function normalise(string|int|float $rate): string
    {
        return bcadd(is_float($rate) ? number_format($rate, self::SCALE, '.', '') : (string) $rate, '0', self::SCALE);
    }

    public static function same(?string $a, ?string $b): bool
    {
        return $a === null || $b === null ? $a === $b : bccomp($a, $b, self::SCALE) === 0;
    }

    /** "0.0250" → "0.025", "2.0000" → "2" */
    public static function display(?string $rate): string
    {
        if ($rate === null || $rate === '') {
            return '—';
        }

        $trimmed = rtrim(rtrim(self::normalise($rate), '0'), '.');

        return $trimmed === '' || $trimmed === '-0' ? '0' : $trimmed;
    }
}
