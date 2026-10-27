<?php

namespace App\Libraries;

class ColorLibrary
{
    public static function darken(string $hex, float $ratio = 0.78): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return self::toHex(
            (int) round($r * $ratio),
            (int) round($g * $ratio),
            (int) round($b * $ratio),
        );
    }

    public static function lighten(string $hex, float $whiteMix = 0.92): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $mix = max(0, min(1, $whiteMix));

        return self::toHex(
            (int) round($r * (1 - $mix) + 255 * $mix),
            (int) round($g * (1 - $mix) + 255 * $mix),
            (int) round($b * (1 - $mix) + 255 * $mix),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [18, 78, 176];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function toHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }
}
