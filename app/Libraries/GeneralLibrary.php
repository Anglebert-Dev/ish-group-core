<?php

namespace App\Libraries;

use Illuminate\Support\Carbon;

class GeneralLibrary
{
    public const PICKUP_LEAD_HOURS = 1;

    public static function response(bool $status, mixed $data = null, string $message = ''): array
    {
        return [
            'status'  => $status,
            'data'    => $data,
            'message' => $message,
        ];
    }

    public static function generateOtp(int $length = 6): string
    {
        return str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Format a date/time in the application timezone (Africa/Kigali).
     */
    public static function formatDateTime(mixed $value, string $format = 'd M Y H:i'): string
    {
        if (blank($value)) {
            return '—';
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->format($format.' T');
    }

    /**
     * Parse a naive datetime string (e.g. datetime-local) as app timezone.
     */
    public static function parseAppDateTime(mixed $value): Carbon
    {
        return Carbon::parse($value, config('app.timezone'));
    }

    /**
     * Earliest allowed pickup: now + lead time in the app timezone.
     */
    public static function earliestPickupAt(?Carbon $from = null): Carbon
    {
        return ($from ?? now(config('app.timezone')))
            ->copy()
            ->addHours(self::PICKUP_LEAD_HOURS);
    }

    /**
     * Value for datetime-local min attributes.
     */
    public static function earliestPickupLocalValue(?Carbon $from = null): string
    {
        return self::earliestPickupAt($from)->format('Y-m-d\TH:i');
    }

    /**
     * Value for date-only min attributes.
     */
    public static function earliestPickupDateValue(?Carbon $from = null): string
    {
        return self::earliestPickupAt($from)->toDateString();
    }
}
