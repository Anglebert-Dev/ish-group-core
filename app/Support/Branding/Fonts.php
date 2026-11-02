<?php

namespace App\Support\Branding;

/**
 * The approved system fonts (docs/branding.md §3.5). One choice drives the
 * app, login, emails, website and printed documents.
 *
 * Files ship with the app, so nothing loads from Google and self-hosted
 * servers work offline:
 *   public/fonts/{key}/{400,500,600,700}.woff2   screens
 *   resources/fonts/{key}/{400,700}.ttf          PDFs (dompdf)
 */
final class Fonts
{
    public const DEFAULT = 'inter';

    /** The family name every page and document uses for the brand font. */
    public const FAMILY = 'BrandFont';

    /** @var array<string, array{label: string, family: ?string, kind: string}> */
    public const LIST = [
        'inter' => ['label' => 'Inter', 'family' => 'Inter', 'kind' => 'sans'],
        'roboto' => ['label' => 'Roboto', 'family' => 'Roboto', 'kind' => 'sans'],
        'open_sans' => ['label' => 'Open Sans', 'family' => 'Open Sans', 'kind' => 'sans'],
        'lato' => ['label' => 'Lato', 'family' => 'Lato', 'kind' => 'sans'],
        'poppins' => ['label' => 'Poppins', 'family' => 'Poppins', 'kind' => 'sans'],
        'montserrat' => ['label' => 'Montserrat', 'family' => 'Montserrat', 'kind' => 'sans'],
        'nunito' => ['label' => 'Nunito', 'family' => 'Nunito', 'kind' => 'sans'],
        'dm_sans' => ['label' => 'DM Sans', 'family' => 'DM Sans', 'kind' => 'sans'],
        'work_sans' => ['label' => 'Work Sans', 'family' => 'Work Sans', 'kind' => 'sans'],
        'source_serif' => ['label' => 'Source Serif', 'family' => 'Source Serif 4', 'kind' => 'serif'],
        'merriweather' => ['label' => 'Merriweather', 'family' => 'Merriweather', 'kind' => 'serif'],
        'playfair' => ['label' => 'Playfair Display', 'family' => 'Playfair Display', 'kind' => 'serif'],
        'system' => ['label' => 'System font', 'family' => null, 'kind' => 'sans'],
    ];

    /** Print-only choices saved before one font drove everything. */
    private const LEGACY = ['sans' => 'inter', 'helvetica' => 'system', 'serif' => 'source_serif', 'times' => 'source_serif'];

    private const WEIGHTS = [400, 500, 600, 700];

    /**
     * The business's own uploaded font for this request, if it uses one
     * (key "custom"; set by BrandingService::font()).
     *
     * @var array{name: string, woff2: string, ttf: ?string}|null
     */
    private static ?array $custom = null;

    /** @param  array{name: string, woff2: string, ttf: ?string}|null  $font  woff2 = public URL, ttf = local file */
    public static function useCustom(?array $font): void
    {
        self::$custom = $font;
    }

    private const FALLBACK = [
        'sans' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
        'serif' => 'Georgia, Cambria, "Times New Roman", Times, serif',
    ];

    /** dompdf's built-in fonts, used when a font has no TTF. */
    private const PDF_FALLBACK = ['sans' => '"DejaVu Sans", sans-serif', 'serif' => '"DejaVu Serif", serif'];

    public static function normalise(?string $key): string
    {
        if ($key === 'custom' && self::$custom) {
            return 'custom';
        }
        $key = self::LEGACY[$key] ?? $key;

        return isset(self::LIST[$key]) ? $key : self::DEFAULT;
    }

    /** @return array<string, string> key => label */
    public static function options(): array
    {
        return array_map(fn (array $font) => $font['label'], self::LIST) + (self::$custom ? ['custom' => self::$custom['name']] : []);
    }

    /** The font's real name (for Excel and email), null for the system font. */
    public static function family(string $key): ?string
    {
        $key = self::normalise($key);

        return $key === 'custom' ? self::$custom['name'] : self::LIST[$key]['family'];
    }

    /** CSS font stack for screens and emails. */
    public static function stack(string $key): string
    {
        if (self::normalise($key) === 'custom') {
            return '"'.self::FAMILY.'", '.self::FALLBACK['sans'];
        }
        $font = self::LIST[self::normalise($key)];

        return ($font['family'] ? '"'.self::FAMILY.'", "'.$font['family'].'", ' : '').self::FALLBACK[$font['kind']];
    }

    /** CSS font stack for dompdf: the bundled TTF, then a built-in font. */
    public static function pdfStack(string $key): string
    {
        $key = self::normalise($key);
        if ($key === 'custom') {
            return (self::$custom['ttf'] ? '"'.self::pdfFamily('custom').'", ' : '').self::PDF_FALLBACK['sans'];
        }

        return (self::LIST[$key]['family'] ? '"'.self::pdfFamily($key).'", ' : '').self::PDF_FALLBACK[self::LIST[$key]['kind']];
    }

    /** @font-face rules for screens (woff2), only the chosen font. */
    public static function faceCss(string $key): string
    {
        $key = self::normalise($key);
        if ($key === 'custom') {
            return '@font-face{font-family:"'.self::FAMILY.'";font-style:normal;font-weight:100 900;font-display:swap;src:url("'.self::$custom['woff2'].'") format("woff2");}';
        }
        if (! self::LIST[$key]['family']) {
            return '';
        }

        $css = '';
        foreach (self::WEIGHTS as $weight) {
            $file = self::nearest(public_path("fonts/{$key}"), $weight, 'woff2');
            if ($file) {
                $url = asset("fonts/{$key}/".basename($file)).'?v='.filemtime($file);
                $css .= '@font-face{font-family:"'.self::FAMILY."\";font-style:normal;font-weight:{$weight};font-display:swap;src:url(\"{$url}\") format(\"woff2\");}";
            }
        }

        return $css;
    }

    /**
     * Every font under its own name ("Preview-{key}"), for the font picker.
     * Browsers only download the ones actually shown.
     */
    public static function previewCss(): string
    {
        $css = '';
        foreach (self::LIST as $key => $font) {
            if (! $font['family']) {
                continue;
            }
            foreach ([400, 700] as $weight) {
                $file = self::nearest(public_path("fonts/{$key}"), $weight, 'woff2');
                if ($file) {
                    $css .= "@font-face{font-family:\"Preview-{$key}\";font-weight:{$weight};font-display:swap;src:url(\"".asset("fonts/{$key}/".basename($file)).'") format("woff2");}';
                }
            }
        }

        return $css;
    }

    /** @return array<string, string> key => CSS stack using the preview names */
    public static function previewStacks(): array
    {
        $stacks = self::$custom ? ['custom' => '"'.self::FAMILY.'", '.self::FALLBACK['sans']] : [];
        foreach (self::LIST as $key => $font) {
            $stacks[$key] = ($font['family'] ? '"Preview-'.$key.'", ' : '').self::FALLBACK[$font['kind']];
        }

        return $stacks;
    }

    /** @font-face rules for dompdf (local TTF files, regular and bold). */
    public static function pdfFaceCss(string $key): string
    {
        $key = self::normalise($key);
        if ($key === 'custom') {
            return self::$custom['ttf'] ? '@font-face{font-family:"'.self::pdfFamily('custom').'";font-style:normal;font-weight:normal;src:url("'.self::$custom['ttf'].'") format("truetype");}' : '';
        }
        if (! self::LIST[$key]['family']) {
            return '';
        }

        $css = '';
        foreach (['normal' => 400, 'bold' => 700] as $name => $weight) {
            $file = self::nearest(resource_path("fonts/{$key}"), $weight, 'ttf');
            if ($file) {
                $css .= '@font-face{font-family:"'.self::pdfFamily($key)."\";font-style:normal;font-weight:{$name};src:url(\"{$file}\") format(\"truetype\");}";
            }
        }

        return $css;
    }

    /** dompdf caches fonts by family name, so each font needs its own. */
    private static function pdfFamily(string $key): string
    {
        return self::FAMILY.'-'.str_replace('_', '-', $key);
    }

    /** The file for a weight, or the closest weight shipped (Lato has no 500/600). */
    private static function nearest(string $dir, int $weight, string $ext): ?string
    {
        $candidates = self::WEIGHTS;
        usort($candidates, fn (int $a, int $b) => [abs($a - $weight), $a] <=> [abs($b - $weight), $b]);

        foreach ($candidates as $candidate) {
            if (is_file("{$dir}/{$candidate}.{$ext}")) {
                return "{$dir}/{$candidate}.{$ext}";
            }
        }

        return null;
    }
}
