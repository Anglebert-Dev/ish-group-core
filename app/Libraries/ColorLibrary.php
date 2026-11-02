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

    /** $amount (0–1) of $b mixed into $a. */
    public static function mix(string $a, string $b, float $amount): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        $t = max(0, min(1, $amount));

        return self::toHex(
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t),
        );
    }

    /** WCAG contrast ratio between two colours (1–21). */
    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** WCAG relative luminance (0 black – 1 white). */
    public static function luminance(string $hex): float
    {
        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /**
     * The nearest colour to $fg that reads on $bg (ratio $min): $fg pushed
     * towards black or white, whichever the background needs.
     */
    public static function readable(string $fg, string $bg, float $min = 4.5): string
    {
        if (self::contrast($fg, $bg) >= $min) {
            return strtoupper($fg);
        }

        $target = self::luminance($bg) > 0.4 ? '#000000' : '#FFFFFF';
        for ($step = 0.05; $step <= 1.0; $step += 0.05) {
            $candidate = self::mix($fg, $target, $step);
            if (self::contrast($candidate, $bg) >= $min) {
                return $candidate;
            }
        }

        return $target;
    }

    /**
     * Like readable(), but keeps the colour's hue and saturation and moves only
     * its lightness: a navy brand on a dark card becomes a brighter indigo,
     * not a grey.
     */
    public static function fit(string $fg, string $bg, float $min = 3.0): string
    {
        if (self::contrast($fg, $bg) >= $min) {
            return strtoupper($fg);
        }

        [$h, $s, $l] = self::toHsl($fg);
        $direction = self::luminance($bg) > 0.4 ? -1 : 1;
        for ($i = 1; $i <= 50; $i++) {
            $candidate = self::fromHsl($h, $s, max(0, min(1, $l + $direction * 0.02 * $i)));
            if (self::contrast($candidate, $bg) >= $min) {
                return $candidate;
            }
        }

        return self::readable($fg, $bg, $min);
    }

    /** @return array{0: float, 1: float, 2: float} hue, saturation, lightness (0–1) */
    public static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    public static function fromHsl(float $h, float $s, float $l): string
    {
        if ($s == 0) {
            $v = (int) round($l * 255);

            return self::toHex($v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hue = function (float $t) use ($p, $q): float {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return self::toHex((int) round($hue($h + 1 / 3) * 255), (int) round($hue($h) * 255), (int) round($hue($h - 1 / 3) * 255));
    }

    /**
     * "#abc", "#aabbcc", "rgb(1, 2, 3)" or "hsl(210, 50%, 40%)" as "#AABBCC"; null if not a colour.
     */
    public static function parse(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#?([0-9a-f]{6})$/', $value, $m)) {
            return '#'.strtoupper($m[1]);
        }
        if (preg_match('/^#?([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return strtoupper('#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3]);
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/', $value, $m)) {
            if (max($m[1], $m[2], $m[3]) > 255) {
                return null;
            }

            return self::toHex((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^hsla?\(\s*(\d{1,3}(?:\.\d+)?)(?:deg)?\s*[, ]\s*(\d{1,3}(?:\.\d+)?)%\s*[, ]\s*(\d{1,3}(?:\.\d+)?)%\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/', $value, $m)) {
            return self::fromHsl(fmod((float) $m[1], 360) / 360, min(100, (float) $m[2]) / 100, min(100, (float) $m[3]) / 100);
        }

        return null;
    }

    /** True when white text reads better on it than dark text. */
    public static function isDark(string $hex): bool
    {
        [$r, $g, $b] = self::rgb($hex);

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150;
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
