<?php

namespace Tests\Feature;

use App\Libraries\ColorLibrary;
use App\Services\Branding\ThemeService;
use App\Support\Branding\ThemeTokens;
use Tests\TestCase;

/**
 * Light, dark and device modes, people's own choice, readability, colour
 * entry (docs/branding.md §3.2–3.3, phase B2).
 */
class ThemeModesTest extends TestCase
{
    public function test_dark_and_device_modes_reach_every_page(): void
    {
        $owner = $this->actingAsStaff();
        $themes = app(ThemeService::class);

        $themes->saveDraft($owner->business, ['mode' => 'dark']);
        $themes->publish($owner->business);
        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 17 24 39', false)->assertSee('color-scheme:dark', false);

        $themes->saveDraft($owner->business, ['mode' => 'system']);
        $themes->publish($owner->business);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('--c-card: 255 255 255', false)
            ->assertSee('@media (prefers-color-scheme: dark){:root{', false);
    }

    public function test_people_choose_their_own_mode_only_when_allowed(): void
    {
        $owner = $this->actingAsStaff();
        $themes = app(ThemeService::class);

        $this->get(route('dashboard'))->assertSee('name="appearance" value="dark"', false);
        $this->post(route('staff.appearance'), ['appearance' => 'dark'])->assertRedirect();
        $this->assertSame('dark', $owner->fresh()->appearance);
        $this->get(route('dashboard'))->assertSee('color-scheme:dark', false);

        // The business's setting is untouched by a person's choice.
        $this->assertSame('light', $themes->mode($owner->business));

        $this->post(route('staff.appearance'), ['appearance' => 'business']);
        $this->assertNull($owner->fresh()->appearance);

        $owner->forceFill(['appearance' => 'dark'])->save();
        $themes->saveDraft($owner->business, ['user_mode' => false]);
        $themes->publish($owner->business);
        $this->get(route('dashboard'))->assertDontSee('color-scheme:dark', false)->assertDontSee('name="appearance"', false);
        $this->post(route('staff.appearance'), ['appearance' => 'light'])->assertForbidden();
    }

    public function test_unreadable_colours_cannot_be_published(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'theme', 'mode' => 'light', 'user_mode' => '1', 'colors' => ['body' => '#EEEEEE']])->assertSessionHasNoErrors();
        $this->get(route('settings.index', ['tab' => 'theme']))->assertOk()->assertSee(__('body.theme_contrast_blocking'));

        $this->post(route('settings.theme.publish'))->assertSessionHas('error');
        $this->assertNull(app(ThemeService::class)->published($owner->business));

        $this->put(route('settings.update'), ['section' => 'theme', 'mode' => 'light', 'colors' => ['body' => '#333333']]);
        $this->post(route('settings.theme.publish'))->assertSessionHas('success');
    }

    public function test_colours_can_be_typed_as_hex_rgb_or_hsl(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'theme', 'mode' => 'light', 'colors' => [
            'page' => 'rgb(243, 245, 249)', 'line' => 'hsl(0, 0%, 85%)', 'card' => '#fff',
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['page' => '#F3F5F9', 'card' => '#FFFFFF', 'line' => '#D9D9D9'], app(ThemeService::class)->draft($owner->business)->payload['colors']);

        $this->put(route('settings.update'), ['section' => 'theme', 'mode' => 'light', 'colors' => ['page' => 'banana']])->assertSessionHasErrors('colors.page');
    }

    public function test_the_theme_tab_offers_modes_palettes_and_dark_colours(): void
    {
        $this->actingAsStaff();

        $this->get(route('settings.index', ['tab' => 'theme']))->assertOk()
            ->assertSee(__('body.theme_mode_system'))
            ->assertSee(__('body.theme_palette_navy'))
            ->assertSee('name="colors_dark[card]"', false)
            ->assertSee('name="colors[chart_1]"', false);
    }

    public function test_built_in_dark_colours_are_readable(): void
    {
        foreach (['#124EB0', '#C2410C', '#0A7A4B', '#111111', '#FDE047'] as $brand) {
            $dark = ThemeTokens::resolve($brand, [], dark: true);
            $this->assertGreaterThanOrEqual(4.5, ColorLibrary::contrast($dark['body'], $dark['card']));
            $this->assertGreaterThanOrEqual(4.5, ColorLibrary::contrast($dark['link'], $dark['card']), $brand);
            $this->assertGreaterThanOrEqual(4.5, ColorLibrary::contrast($dark['brand_dark'], $dark['card']), $brand);
        }
    }
}
