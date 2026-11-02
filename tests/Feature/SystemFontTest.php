<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Services\Branding\BrandingService;
use App\Services\Printing\PrintService;
use App\Support\Branding\Fonts;
use App\Support\Mail\BrandedMail;
use App\Support\Printing\PrintDesign;
use App\Support\Printing\SampleDocument;
use App\Support\Settings\BusinessSettings;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * One font for the whole system (docs/branding.md §3.5, phase B0).
 */
class SystemFontTest extends TestCase
{
    public function test_every_font_ships_its_files(): void
    {
        foreach (Fonts::LIST as $key => $font) {
            if (! $font['family']) {
                continue;
            }
            $this->assertFileExists(public_path("fonts/{$key}/400.woff2"), $key);
            $this->assertFileExists(public_path("fonts/{$key}/700.woff2"), $key);
            $this->assertFileExists(resource_path("fonts/{$key}/400.ttf"), $key);
            $this->assertFileExists(resource_path("fonts/{$key}/700.ttf"), $key);
        }
    }

    public function test_the_chosen_font_is_used_by_the_app_and_audited(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => '#124EB0', 'font' => 'poppins'])->assertSessionHasNoErrors();

        $this->assertSame('poppins', app(BrandingService::class)->font($owner->business->fresh()));
        $this->assertTrue(ActivityLog::query()->where('description', 'like', '%branding.font%')->exists());

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('fonts/poppins/400.woff2', false)
            ->assertSee('--font-body: "BrandFont", "Poppins"', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_an_unknown_font_is_refused(): void
    {
        $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => '#124EB0', 'font' => 'comic_sans'])->assertSessionHasErrors('font');
    }

    public function test_the_system_font_loads_nothing(): void
    {
        $this->assertSame('', Fonts::faceCss('system'));
        $this->assertStringStartsWith('ui-sans-serif', Fonts::stack('system'));
        $this->assertStringStartsWith('"DejaVu Sans"', Fonts::pdfStack('system'));
    }

    public function test_choices_saved_before_one_font_still_work(): void
    {
        $this->assertSame('inter', Fonts::normalise('sans'));
        $this->assertSame('source_serif', Fonts::normalise('serif'));
        $this->assertSame('inter', Fonts::normalise(null));

        // An old print-only choice gives way to the system font.
        [$business] = $this->makeBusiness();
        app(BusinessSettings::class)->set('branding.font', 'lato', $business);
        app(BusinessSettings::class)->set('print.font', 'sans', $business);
        $this->assertSame('lato', PrintDesign::for($business)->font);

        app(BusinessSettings::class)->set('print.font', 'merriweather', $business);
        $this->assertSame('merriweather', PrintDesign::for($business)->font, 'the designer can still pick its own');
    }

    public function test_pdfs_embed_the_chosen_font(): void
    {
        [$business] = $this->makeBusiness();

        foreach (['merriweather' => 'Merriweather', 'lato' => 'Lato'] as $key => $name) {
            app(BusinessSettings::class)->set('branding.font', $key, $business);
            $pdf = app(PrintService::class)->pdfBytes(SampleDocument::make(), $business->fresh());
            $this->assertMatchesRegularExpression('/FontName \/[A-Z]+\+'.$name.'-Regular/', $pdf, $key);
            $this->assertMatchesRegularExpression('/FontName \/[A-Z]+\+'.$name.'-Bold/', $pdf, $key);
        }
    }

    public function test_emails_use_the_font(): void
    {
        [$business] = $this->makeBusiness();
        app(BusinessSettings::class)->set('branding.font', 'nunito', $business);

        $html = (string) BrandedMail::make($business->fresh(), 'Hello', ['Line'])->render();

        $this->assertStringContainsString('fonts/nunito/400.woff2', $html);
        $this->assertStringContainsString('Nunito', $html);
    }

    public function test_no_page_loads_fonts_from_google(): void
    {
        $views = collect(File::allFiles(resource_path('views')))
            ->merge(collect(glob(base_path('Modules/*/resources/views'), GLOB_ONLYDIR))->flatMap(fn ($dir) => File::allFiles($dir)));

        foreach ($views as $file) {
            $this->assertStringNotContainsString('fonts.googleapis.com', $file->getContents(), $file->getRelativePathname());
        }
    }
}
