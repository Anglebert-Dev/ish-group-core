<?php

namespace App\Support\Branding;

use App\Libraries\ColorLibrary;

/**
 * Readability of a theme (docs/branding.md §3.2): text against what it sits
 * on, using the WCAG contrast ratio. "Blocking" problems stop publishing;
 * the rest are warnings. Each comes with a readable suggestion.
 */
final class ThemeContrast
{
    /** [text token, background token, minimum ratio, blocks publishing] */
    private const PAIRS = [
        ['body', 'card', 4.5, true],
        ['ink', 'card', 4.5, true],
        ['body', 'page', 4.5, true],
        ['sidebar_ink', 'sidebar', 4.5, true],
        ['header_ink', 'header', 4.5, true],
        ['muted', 'card', 3.0, true],
        ['muted', 'card', 4.5, false],
        ['link', 'card', 4.5, false],
        ['danger', 'card', 3.0, false],
        ['success', 'card', 3.0, false],
    ];

    /**
     * @param  array<string, string>  $tokens
     * @return list<array{mode: string, text: string, background: string, ratio: float, min: float, blocking: bool, suggestion: string}>
     */
    public static function check(array $tokens, string $mode = 'light'): array
    {
        $issues = [];
        foreach (self::PAIRS as [$fg, $bg, $min, $blocking]) {
            $ratio = ColorLibrary::contrast($tokens[$fg], $tokens[$bg]);
            // A blocking failure is reported once, not again as a warning.
            if ($ratio < $min && ! collect($issues)->contains(fn ($i) => $i['text'] === $fg && $i['background'] === $bg)) {
                $issues[] = [
                    'mode' => $mode, 'text' => $fg, 'background' => $bg, 'ratio' => round($ratio, 2), 'min' => $min,
                    'blocking' => $blocking, 'suggestion' => ColorLibrary::readable($tokens[$fg], $tokens[$bg], max($min, 4.5)),
                ];
            }
        }

        // White button text on the brand colour (set on Look, so only a warning here).
        $ratio = ColorLibrary::contrast('#FFFFFF', $tokens['brand']);
        if ($ratio < 4.5) {
            $issues[] = [
                'mode' => $mode, 'text' => 'button_text', 'background' => 'brand', 'ratio' => round($ratio, 2), 'min' => 4.5,
                'blocking' => false, 'suggestion' => ColorLibrary::readable($tokens['brand'], '#FFFFFF'),
            ];
        }

        return $issues;
    }
}
