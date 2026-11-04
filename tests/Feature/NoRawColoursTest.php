<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Pages use the design tokens (bg-card, text-ink…), never real colours: a
 * business can change every colour and each user can pick dark mode, so
 * bg-white or text-gray-500 would break on someone's screen
 * (docs/branding.md, docs/module-building-guide.md §11).
 */
class NoRawColoursTest extends TestCase
{
    private const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

    /** Allowed to have their own colours. */
    private const EXEMPT = [
        'resources/views/install/',                  // runs before any theme exists
        'Modules/Frontend/resources/views/',         // the public website's own design
        'resources/views/components/layouts/auth',   // white text over the sign-in photo
        'resources/views/components/dashboard/preview-banner', // sits on a fixed dark bar
        'resources/views/components/common/logo',    // white initial on the brand colour
    ];

    public function test_views_use_design_tokens_not_real_colours(): void
    {
        $utility = '(?:bg|text|border|ring|divide|from|to|via|fill|stroke|placeholder|outline|decoration)';
        // bg-black/50 is a see-through overlay and fine; solid white or black is not.
        $raw = '/(?<![\w\/-])(?:[a-z-]+:)*'.$utility.'-(?:(?:'.self::PALETTE.')-\d{2,3}|white|black|\[#[0-9a-fA-F]+\])(?:\/\d+)?(?![\w-])/';

        $found = [];
        foreach ([...File::allFiles(resource_path('views')), ...$this->moduleViews()] as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());

            if (collect(self::EXEMPT)->contains(fn (string $exempt) => str_starts_with($path, $exempt))) {
                continue;
            }

            preg_match_all($raw, $file->getContents(), $matches);
            $solid = array_filter($matches[0], fn (string $class) => ! preg_match('/-(?:white|black)\/\d+$/', $class));

            if ($solid !== []) {
                $found[] = $path.': '.implode(', ', array_unique($solid));
            }
        }

        $this->assertSame([], $found, "Use design tokens (bg-card, text-ink, text-muted, border-line…):\n".implode("\n", $found));
    }

    public function test_no_page_needs_the_internet_for_its_styles(): void
    {
        foreach ([...File::allFiles(resource_path('views')), ...$this->moduleViews()] as $file) {
            $this->assertStringNotContainsString('cdn.tailwindcss.com', $file->getContents(), $file->getPathname());
        }

        $this->assertFileExists(public_path('js/tailwind-3.4.17.js'));
    }

    private function moduleViews(): array
    {
        return collect(File::glob(base_path('Modules/*/resources/views')))
            ->flatMap(fn (string $dir) => File::allFiles($dir))->all();
    }
}
