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

        // Buttons must stand out from the cards (the brand is fitted automatically,
        // but a dark-mode brand chosen on the Theme tab is used as given).
        $ratio = ColorLibrary::contrast($tokens['brand'], $tokens['card']);
        if ($ratio < 3.0) {
            $issues[] = [
                'mode' => $mode, 'text' => 'brand', 'background' => 'card', 'ratio' => round($ratio, 2), 'min' => 3.0,
                'blocking' => true, 'suggestion' => ColorLibrary::readable($tokens['brand'], $tokens['card'], 3.0),
            ];
        }

        return $issues;
    }
}
