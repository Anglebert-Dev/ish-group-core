<?php

namespace Tests\Feature;

use App\Services\Branding\ThemeService;
use App\Support\Branding\ThemeSchema;
use Tests\TestCase;

/**
 * Layout & typography (docs/branding.md §3.4–3.5, phase B3).
 */
class ThemeLayoutTest extends TestCase
{
    public function test_defaults_keep_todays_look(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-density="comfortable"', false)
            ->assertSee('data-cards="bordered"', false)
            ->assertSee('--sidebar-w-open:16rem', false)
            ->assertSee('--radius-xl:0.75rem', false)
            ->assertSee('--content-max:none', false)
            ->assertSee('id="back-to-top"', false)
            ->assertSee('id="page-loading-bar"', false)
            ->assertSee('css/theme.css', false);
    }

    public function test_layout_choices_are_drafted_previewed_and_published(): void
    {
        $owner = $this->actingAsStaff();
        $layout = collect(ThemeSchema::LAYOUT)->map(fn ($options) => $options[0])->all();

        $this->get(route('settings.index', ['tab' => 'layout']))->assertOk()->assertSee(__('body.layout_corners_sharp'));

        $this->put(route('settings.update'), [
            'section' => 'layout',
            'layout' => ['density' => 'compact', 'corners' => 'sharp', 'buttons' => 'pill', 'sidebar_width' => 'compact', 'sidebar_start' => 'collapsed', 'content_width' => 'standard', 'font_size' => 'large'] + $layout,
            'heading_font' => 'playfair',
            'switches' => ['page_subtitles' => '1', 'motion' => '1'],
        ])->assertRedirect(route('settings.index', ['tab' => 'layout']));

        // Not live yet.
        $this->get(route('dashboard'))->assertSee('data-density="comfortable"', false);

        $this->post(route('settings.theme.preview'));
        $this->get(route('dashboard'))->assertSee('data-density="compact"', false);

        $this->post(route('settings.theme.publish'))->assertSessionHas('success');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-density="compact"', false)
            ->assertSee('data-sidebar-start="collapsed"', false)
            ->assertSee('--radius-button:9999px', false)
            ->assertSee('--sidebar-w-open:14rem', false)
            ->assertSee('--content-max:80rem', false)
            ->assertSee('html{font-size:17px}', false)
            ->assertSee('font-family:"BrandHeading"', false)
            ->assertSee('fonts/playfair/', false)
            ->assertDontSee('id="back-to-top"', false)
            ->assertDontSee('id="page-loading-bar"', false);

        $this->assertSame('playfair', app(ThemeService::class)->effective($owner->business)['heading_font']);
    }

    public function test_unknown_choices_are_refused(): void
    {
        $this->actingAsStaff();
        $layout = collect(ThemeSchema::LAYOUT)->map(fn ($options) => $options[0])->all();

        $this->put(route('settings.update'), ['section' => 'layout', 'layout' => ['corners' => 'wavy'] + $layout, 'heading_font' => 'same'])->assertSessionHasErrors('layout.corners');
        $this->put(route('settings.update'), ['section' => 'layout', 'layout' => $layout, 'heading_font' => 'comic'])->assertSessionHasErrors('heading_font');
    }

    public function test_choosing_the_same_heading_font_loads_nothing_extra(): void
    {
        $owner = $this->actingAsStaff();
        app(ThemeService::class)->saveDraft($owner->business, ['heading_font' => 'inter']);
        app(ThemeService::class)->publish($owner->business);

        $this->get(route('dashboard'))->assertOk()->assertSee('--font-heading:var(--font-body)', false)->assertDontSee('BrandHeading', false);
    }
}
