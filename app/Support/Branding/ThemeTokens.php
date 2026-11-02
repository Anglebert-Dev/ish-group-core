<?php

namespace App\Support\Branding;

use App\Libraries\ColorLibrary;

/**
 * The design tokens every page uses (docs/branding.md §2.3): one named
 * colour per role, never a hex code in a view. Tokens are CSS variables
 * holding RGB channels ("17 24 39"), so Tailwind's opacity classes
 * (bg-brand/20) work, and a business theme only swaps the variables.
 */
final class ThemeTokens
{
    /** The built-in light theme: today's look. */
    public const DEFAULTS = [
        'page' => '#F8FAFC',     // app background
        'card' => '#FFFFFF',     // cards, panels, inputs
        'surface' => '#F8FAFC',  // subtle fills inside cards
        'line' => '#E5E7EB',     // borders and separators
        'ink' => '#111827',      // headings, strong text
        'body' => '#1F2937',     // normal text
        'muted' => '#6B7280',    // secondary text
        'success' => '#16A34A',
        'warning' => '#F59E0B',
        'danger' => '#DC2626',
        'info' => '#0284C7',
        'sidebar' => '#FFFFFF',
        'sidebar_ink' => '#1F2937',
        'header' => '#FFFFFF',
        'header_ink' => '#1F2937',
    ];

    /** Tokens worked out from the brand colour unless set. */
    public const DERIVED = ['brand', 'brand_dark', 'brand_light', 'accent', 'link'];

    /**
     * Every token as a hex colour: defaults, then the brand, then overrides.
     *
     * @param  array<string, ?string>  $overrides
     * @return array<string, string>
     */
    public static function resolve(string $brand, array $overrides = []): array
    {
        $valid = fn ($hex) => is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? strtoupper($hex) : null;
        $brand = $valid($brand) ?? '#124EB0';

        $tokens = self::DEFAULTS + [
            'brand' => $brand,
            'brand_dark' => ColorLibrary::darken($brand),
            'brand_light' => ColorLibrary::lighten($brand),
            'accent' => $brand,
            'link' => $brand,
        ];

        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $tokens) && ($hex = $valid($value))) {
                $tokens[$key] = $hex;
            }
        }

        return $tokens;
    }

    /**
     * The :root block. Brand colours are also given as plain hex
     * (--brand, --brand-dark, --brand-light) for hand-written CSS.
     *
     * @param  array<string, string>  $tokens
     */
    public static function css(array $tokens): string
    {
        $vars = [];
        foreach ($tokens as $key => $hex) {
            $vars[] = '--c-'.str_replace('_', '-', $key).': '.self::channels($hex);
        }
        foreach (['brand', 'brand_dark', 'brand_light'] as $key) {
            $vars[] = '--'.str_replace('_', '-', $key).': '.$tokens[$key];
        }

        return ':root{'.implode(';', $vars).'}';
    }

    /**
     * The Tailwind config every layout shares.
     *
     * @return array<string, mixed>
     */
    public static function tailwind(): array
    {
        $color = fn (string $key) => 'rgb(var(--c-'.str_replace('_', '-', $key).') / <alpha-value>)';

        return ['theme' => ['extend' => [
            'fontFamily' => ['display' => ['var(--font-body)'], 'sans' => ['var(--font-body)']],
            'colors' => [
                'brand' => ['DEFAULT' => $color('brand'), 'dark' => $color('brand_dark'), 'light' => $color('brand_light')],
                'accent' => $color('accent'),
                'link' => $color('link'),
                'page' => $color('page'),
                'card' => $color('card'),
                'surface' => $color('surface'),
                'line' => $color('line'),
                'ink' => $color('ink'),
                'body' => $color('body'),
                'muted' => $color('muted'),
                'success' => $color('success'),
                'warning' => $color('warning'),
                'danger' => $color('danger'),
                'info' => $color('info'),
                'sidebar' => ['DEFAULT' => $color('sidebar'), 'ink' => $color('sidebar_ink')],
                'header' => ['DEFAULT' => $color('header'), 'ink' => $color('header_ink')],
            ],
            'boxShadow' => ['card' => '0 1px 2px 0 rgba(17, 24, 39, 0.04)'],
        ]]];
    }

    private static function channels(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return hexdec(substr($hex, 0, 2)).' '.hexdec(substr($hex, 2, 2)).' '.hexdec(substr($hex, 4, 2));
    }
}
