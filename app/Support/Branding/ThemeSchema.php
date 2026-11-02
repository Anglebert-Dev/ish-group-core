<?php

namespace App\Support\Branding;

use App\Libraries\ColorLibrary;

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
        'chart_1', 'chart_2', 'chart_3', 'chart_4', 'chart_5', 'chart_6',
    ];

    public const DEFAULT_MODE = 'light';

    /**
     * Ready-made colour sets for the Theme tab (light colours; blank = default).
     * Applying one fills the form; nothing is saved until "Save draft".
     */
    public const PALETTES = [
        'default' => [],
        'navy' => ['page' => '#F3F5F9', 'sidebar' => '#0B1F3A', 'sidebar_ink' => '#E2E8F0'],
        'charcoal' => ['page' => '#F5F5F4', 'sidebar' => '#1F2937', 'sidebar_ink' => '#F3F4F6', 'line' => '#E7E5E4'],
        'warm' => ['page' => '#FAF7F2', 'surface' => '#F5EFE6', 'line' => '#E7DFD3', 'sidebar' => '#FFFDF9', 'ink' => '#1C1917', 'body' => '#292524', 'muted' => '#78716C'],
        'forest' => ['page' => '#F4F7F5', 'sidebar' => '#0F2A1D', 'sidebar_ink' => '#E3F1E8', 'line' => '#DDE6E0'],
        'slate' => ['page' => '#EEF2F6', 'card' => '#FFFFFF', 'surface' => '#F1F5F9', 'line' => '#D9E1EA', 'header' => '#F8FAFC', 'sidebar' => '#F8FAFC'],
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function clean(array $payload): array
    {
        $colors = fn (mixed $given) => collect(self::COLORS)
            ->mapWithKeys(fn ($key) => [$key => is_array($given) ? ColorLibrary::parse(is_string($given[$key] ?? null) ? $given[$key] : null) : null])
            ->filter()
            ->all();

        return array_filter([
            'colors' => $colors($payload['colors'] ?? null),
            'colors_dark' => $colors($payload['colors_dark'] ?? null) + array_filter(['brand' => ColorLibrary::parse(is_string($payload['colors_dark']['brand'] ?? null) ? $payload['colors_dark']['brand'] : null)]),
            // Light, dark, or follow each device.
            'mode' => in_array($payload['mode'] ?? null, ThemeTokens::MODES, true) ? $payload['mode'] : null,
            // May each person pick their own light/dark? (Unset = yes.)
            'user_mode' => array_key_exists('user_mode', $payload) ? (bool) $payload['user_mode'] : null,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
