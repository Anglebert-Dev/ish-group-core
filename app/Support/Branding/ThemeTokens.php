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
        // Categorical colours: charts, quick-action tiles.
        'chart_1' => '#2563EB',
        'chart_2' => '#7C3AED',
        'chart_3' => '#4F46E5',
        'chart_4' => '#DB2777',
        'chart_5' => '#0D9488',
        'chart_6' => '#EA580C',
    ];

    /** The built-in dark theme. */
    public const DARK_DEFAULTS = [
        'page' => '#0B1120',
        'card' => '#111827',
        'surface' => '#1A2332',
        'line' => '#2B3546',
        'ink' => '#F9FAFB',
        'body' => '#E5E7EB',
        'muted' => '#9CA3AF',
        'success' => '#22C55E',
        'warning' => '#FBBF24',
        'danger' => '#F87171',
        'info' => '#38BDF8',
        'sidebar' => '#0F172A',
        'sidebar_ink' => '#E5E7EB',
        'header' => '#111827',
        'header_ink' => '#E5E7EB',
        'chart_1' => '#60A5FA',
        'chart_2' => '#A78BFA',
        'chart_3' => '#818CF8',
        'chart_4' => '#F472B6',
        'chart_5' => '#2DD4BF',
        'chart_6' => '#FB923C',
    ];

    public const MODES = ['light', 'dark', 'system'];

    /** Tokens worked out from the brand colour unless set. */
    public const DERIVED = ['brand', 'brand_dark', 'brand_light', 'accent', 'link'];

    /**
     * Every token as a hex colour: defaults, then the brand, then overrides.
     *
     * @param  array<string, ?string>  $overrides
     * @return array<string, string>
     */
    public static function resolve(string $brand, array $overrides = [], bool $dark = false): array
    {
        $valid = fn ($hex) => is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? strtoupper($hex) : null;
        $brand = $valid($brand) ?? '#124EB0';

        $tokens = $dark
            // On dark: "light" brand is a dim tint (active menu item), "dark" brand
            // is a brighter shade (its text), links lifted so they read.
            ? self::DARK_DEFAULTS + [
                'brand' => $brand,
                'brand_dark' => ColorLibrary::readable(ColorLibrary::lighten($brand, 0.35), self::DARK_DEFAULTS['card']),
                'brand_light' => ColorLibrary::mix(self::DARK_DEFAULTS['card'], $brand, 0.22),
                'accent' => $brand,
                'link' => ColorLibrary::readable($brand, self::DARK_DEFAULTS['card']),
            ]
            : self::DEFAULTS + [
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
     * The :root block for a mode: light, dark, or system (light, with the
     * dark set when the device asks for it). Brand colours are also given as
     * plain hex (--brand, --brand-dark, --brand-light) for hand-written CSS.
     *
     * @param  array<string, string>  $light
     * @param  array<string, string>  $dark
     */
    public static function css(array $light, array $dark = [], string $mode = 'light'): string
    {
        return match (true) {
            $mode === 'dark' && $dark !== [] => ':root{'.self::vars($dark).';color-scheme:dark}',
            $mode === 'system' && $dark !== [] => ':root{'.self::vars($light).';color-scheme:light}@media (prefers-color-scheme: dark){:root{'.self::vars($dark).';color-scheme:dark}}',
            default => ':root{'.self::vars($light).';color-scheme:light}',
        };
    }

    /** @param  array<string, string>  $tokens */
    private static function vars(array $tokens): string
    {
        $vars = [];
        foreach ($tokens as $key => $hex) {
            $vars[] = '--c-'.str_replace('_', '-', $key).': '.self::channels($hex);
        }
        foreach (['brand', 'brand_dark', 'brand_light'] as $key) {
            $vars[] = '--'.str_replace('_', '-', $key).': '.$tokens[$key];
        }

        return implode(';', $vars);
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
                'chart' => collect(range(1, 6))->mapWithKeys(fn ($i) => [(string) $i => $color('chart_'.$i)])->all(),
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
