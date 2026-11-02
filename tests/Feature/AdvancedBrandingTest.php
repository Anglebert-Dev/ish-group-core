<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Services\Branding\ThemeService;
use App\Support\Branding\Fonts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

/**
 * Custom CSS, own font, own words, home-screen app, package extras
 * (docs/branding.md §3.13, 3.17, §2.6, phase B10).
 */
class AdvancedBrandingTest extends TestCase
{
    protected function tearDown(): void
    {
        Fonts::useCustom(null);
        parent::tearDown();
    }

    public function test_custom_css_is_sanitised_scoped_drafted_and_published(): void
    {
        $owner = $this->actingAsStaff();
        $this->get(route('settings.index', ['tab' => 'advanced']))->assertOk()->assertSee('name="custom_css"', false);

        $this->put(route('settings.update'), ['section' => 'advanced', 'custom_css' => '@import url(x.css); h1{color:red}'])->assertSessionHasErrors('custom_css');

        $this->put(route('settings.update'), ['section' => 'advanced', 'custom_css' => 'h1 { letter-spacing: 1px; position: fixed }'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertDontSee('id="business-css"', false);

        app(ThemeService::class)->publish($owner->business);
        $this->get(route('dashboard'))->assertOk()->assertSee('<style id="business-css">.app-content h1{letter-spacing: 1px}</style>', false);
        $this->get(route('staff.login'))->assertDontSee('business-css', false);

        // The platform can switch it off everywhere.
        System::addProperty('allow_custom_css', '0');
        $this->get(route('dashboard'))->assertDontSee('id="business-css"', false);
    }

    public function test_an_own_font_needs_the_licence_box_and_then_serves_everywhere(): void
    {
        Storage::fake('public');
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'advanced', 'font_name' => 'Acme Sans', 'font_woff2' => UploadedFile::fake()->create('acme.woff2', 20)])
            ->assertSessionHasErrors('font_licence');

        $this->put(route('settings.update'), ['section' => 'advanced', 'font_name' => 'Acme Sans', 'font_woff2' => UploadedFile::fake()->create('acme.woff2', 20), 'font_licence' => '1'])
            ->assertSessionHasNoErrors();
        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => '#124EB0', 'font' => 'custom'])->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk()->assertSee('storage/branding/fonts/', false)->assertSee('font-weight:100 900', false);
        $this->get(route('settings.index', ['tab' => 'look']))->assertSee('Acme Sans');
    }

    public function test_own_words_replace_terms_across_the_app(): void
    {
        $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'advanced', 'words' => ['body.customers' => 'Clients', 'body.not_a_term' => 'Nope']])->assertSessionHasNoErrors();

        $this->get(route('customers.index'))->assertOk()->assertSee('title="Clients"', false);
        $this->assertNotSame('Nope', __('body.not_a_term'));
        // Everything else in the group still loads.
        $this->assertSame('Dashboard', __('body.dashboard'));
    }

    public function test_the_home_screen_manifest(): void
    {
        $owner = $this->actingAsStaff();
        $owner->business->update(['brand_color' => '#0A7A4B']);

        $this->get(route('manifest'))->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', $owner->business->name)
            ->assertJsonPath('theme_color', '#0A7A4B')
            ->assertJsonPath('display', 'standalone');
        $this->get(route('dashboard'))->assertSee('rel="manifest"', false);
    }

    public function test_packages_decide_extras_and_older_packages_keep_everything(): void
    {
        $listed = new Subscription(['snapshot' => ['capabilities' => ['branding.fonts']]]);
        $older = new Subscription(['snapshot' => ['modules' => []]]);

        $this->assertTrue($listed->allowsCapability('branding.fonts'));
        $this->assertFalse($listed->allowsCapability('branding.custom_css'));
        $this->assertTrue($older->allowsCapability('branding.custom_css'));
    }
}
