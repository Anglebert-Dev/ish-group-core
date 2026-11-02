<?php

namespace Tests\Feature;

use App\Libraries\ColorLibrary;
use App\Support\Branding\ThemeTokens;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Design tokens (docs/branding.md §2.3, §4): views use colour names, never
 * fixed colours, so a business's theme (and dark mode) reaches every page,
 * including modules added later.
 */
class ThemeTokensTest extends TestCase
{
    /** Pages that are fixed by design: no business yet, or dark on purpose. */
    private const ALLOWED = [
        'resources/views/install/',             // before install there is no business
        'resources/views/print/',               // printed documents (own design settings)
        'resources/views/emails/',              // mail apps: inline colours
        'Modules/Frontend/resources/views/components/footer.blade.php', // dark footer
        'Modules/Frontend/resources/views/pages/home.blade.php',        // dark hero
    ];

    public function test_views_use_colour_names_not_fixed_colours(): void
    {
        $raw = '/(?<![\w\/-])(?:[a-z-]+:)*(?:bg|text|border(?:-[trblxy])?|ring|divide|shadow|from|to|via|fill|stroke|outline|placeholder)-(?:gray|slate|zinc|neutral|stone|red|rose|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink)-\d+|(?<![\w\/-])(?:[a-z-]+:)*bg-white(?![\w\/-])/';

        $files = collect(File::allFiles(resource_path('views')))
            ->merge(collect(glob(base_path('Modules/*/resources/views'), GLOB_ONLYDIR))->flatMap(fn ($dir) => File::allFiles($dir)))
            ->merge(File::allFiles(public_path('js')));

        $found = [];
        foreach ($files as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            if (collect(self::ALLOWED)->contains(fn ($allowed) => str_starts_with($path, $allowed))) {
                continue;
            }
            // Text colours flip in dark mode, so they never make a background (use bg-night or bg-black/50).
            if (preg_match_all($raw, $file->getContents(), $matches) | preg_match_all('/(?<![\w\/-])(?:[a-z-]+:)*bg-(?:ink|body)(?:\/\d+)?(?![\w-])/', $file->getContents(), $inverse)) {
                $matches[0] = [...$matches[0], ...$inverse[0]];
                $found[] = $path.': '.implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertSame([], $found, "Use colour names (bg-card, bg-surface, text-ink, text-body, text-muted, border-line, bg-danger/10, text-success, bg-chart-1…), not fixed colours.\n".implode("\n", $found));
    }

    public function test_text_on_a_filled_colour_uses_its_on_colour(): void
    {
        // White on the brand (or a status fill) vanishes when the brand is light:
        // text-on-brand / text-on-danger… pick white or dark for each business.
        $files = collect(File::allFiles(resource_path('views')))
            ->merge(collect(glob(base_path('Modules/*/resources/views'), GLOB_ONLYDIR))->flatMap(fn ($dir) => File::allFiles($dir)))
            ->merge(File::allFiles(public_path('js')));

        $found = [];
        foreach ($files as $file) {
            if (str_contains($file->getPathname(), '/views/install/')) {
                continue; // before install: no business, fixed colours
            }
            foreach (explode("\n", $file->getContents()) as $number => $line) {
                if (preg_match('/(?<![\w-])bg-(?:brand|accent|danger|success|warning|info)(?![\w\/-])/', $line) && preg_match('/(?<![\w\/-])(?:[a-z-]+:)*text-white(?![\w\/-])/', $line)) {
                    $found[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $found, "Use text-on-brand, text-on-danger… on filled colours:\n".implode("\n", $found));
    }

    public function test_the_brand_stays_visible_in_every_mode(): void
    {
        foreach (['#080231', '#000000', '#FFFFFF', '#FDE047', '#124EB0'] as $brand) {
            foreach ([false, true] as $dark) {
                $tokens = ThemeTokens::resolve($brand, [], $dark);
                $this->assertGreaterThanOrEqual(3.0, ColorLibrary::contrast($tokens['brand'], $tokens['card']), "$brand ".($dark ? 'dark' : 'light'));
                $this->assertGreaterThanOrEqual(4.5, ColorLibrary::contrast($tokens['on_brand'], $tokens['brand']) + 0.6, "$brand button text");
            }
        }

        // A colour that already stands out is left exactly as chosen.
        $this->assertSame('#124EB0', ThemeTokens::resolve('#124EB0')['brand']);
        // A dark-mode brand chosen on the Theme tab is used as given.
        $this->assertSame('#FF00AA', ThemeTokens::resolve('#080231', ['brand' => '#FF00AA'], dark: true)['brand']);
    }

    public function test_tokens_follow_the_brand_and_ignore_bad_overrides(): void
    {
        $tokens = ThemeTokens::resolve('#0a7a4b', ['card' => '#fafafa', 'ink' => 'red', 'nope' => '#000000']);

        $this->assertSame('#0A7A4B', $tokens['brand']);
        $this->assertSame('#0A7A4B', $tokens['link'], 'links follow the brand unless set');
        $this->assertSame('#FAFAFA', $tokens['card']);
        $this->assertSame(ThemeTokens::DEFAULTS['ink'], $tokens['ink']);
        $this->assertArrayNotHasKey('nope', $tokens);
        $this->assertSame('#124EB0', ThemeTokens::resolve('not a colour')['brand']);
    }

    public function test_pages_get_the_tokens_and_one_shared_config(): void
    {
        $owner = $this->actingAsStaff();
        $owner->business->update(['brand_color' => '#0A7A4B']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('--c-card: 255 255 255', false)
            ->assertSee('--c-brand: 10 122 75', false)
            ->assertSee('--brand: #0A7A4B', false)
            ->assertSee('rgb(var(--c-ink) / <alpha-value>)', false);
    }
}
