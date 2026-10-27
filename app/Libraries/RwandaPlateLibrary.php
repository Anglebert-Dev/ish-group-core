<?php

namespace App\Libraries;

class RwandaPlateLibrary
{
    /**
     * Civilian Rwandan plates: three letters (must start with R), three digits, one letter.
     * Stored and shown as "RAD 123 A".
     */
    public const PATTERN = '/^R[A-Z]{2} \d{3} [A-Z]$/';

    public const EXAMPLE = 'RAD 123 A';

    public static function normalize(?string $value): ?string
    {
        $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)) ?? '';

        if ($raw === '') {
            return null;
        }

        // Strict shape: LLL DDD L (first letter is R).
        if (! preg_match('/^(R[A-Z]{2})(\d{3})([A-Z])$/', $raw, $matches)) {
            return null;
        }

        return $matches[1].' '.$matches[2].' '.$matches[3];
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    public static function compact(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)) ?? '';
    }
}
