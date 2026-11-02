<?php

namespace App\Support\Branding;

/**
 * What a theme document may hold (docs/branding.md §2.1). Anything else is
 * dropped, so a stored theme can never carry a bad value onto a page.
 * Later phases add sections here (mode, layout, typography…).
 */
final class ThemeSchema
{
    /** Colours a business may set; brand itself stays on Look (it is identity). */
    public const COLORS = [
        'accent', 'link', 'page', 'card', 'surface', 'line', 'ink', 'body', 'muted',
        'success', 'warning', 'danger', 'info', 'sidebar', 'sidebar_ink', 'header', 'header_ink',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function clean(array $payload): array
    {
        $colors = [];
        foreach (self::COLORS as $key) {
            $value = $payload['colors'][$key] ?? null;
            if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $colors[$key] = strtoupper($value);
            }
        }

        return array_filter(['colors' => $colors]);
    }
}
