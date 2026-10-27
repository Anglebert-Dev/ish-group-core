<?php

namespace App\Libraries;

use Illuminate\Support\Carbon;

class GeneralLibrary
{
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
     * Format a date/time in the application timezone.
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
}
