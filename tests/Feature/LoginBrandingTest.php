<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Services\Branding\BrandingService;
use App\Support\Branding\LoginBranding;
use App\Support\Settings\BusinessSettings;
use Tests\TestCase;

/**
 * Login & sign-in pages in the business's brand (docs/branding.md §3.6, phase B5).
 */
class LoginBrandingTest extends TestCase
{
    public function test_the_login_page_tab_saves_a_unique_link_and_texts(): void
    {
        $owner = $this->actingAsStaff();
        $this->get(route('settings.index', ['tab' => 'login']))->assertOk()->assertSee(__('body.login_handle'));

        $this->put(route('settings.update'), [
            'section' => 'login', 'login_handle' => 'Kigali-Sun', 'login_heading' => 'Murakaza neza', 'login_welcome' => 'Sign in to the factory.',
            'login_footer' => 'Trouble? Call IT.', 'login_show_contact' => '1',
        ])->assertSessionHasNoErrors();

        $page = app(BrandingService::class)->loginPage($owner->business);
        $this->assertSame('kigali-sun', $page['handle']);
        $this->assertSame('Murakaza neza', $page['heading']);
        $this->assertTrue($page['show_contact']);

        $this->put(route('settings.update'), ['section' => 'login', 'login_handle' => 'a b'])->assertSessionHasErrors('login_handle');

        [$other] = $this->makeBusiness();
        app(BusinessSettings::class)->set('branding.login_handle', 'taken-name', $other);
        $this->put(route('settings.update'), ['section' => 'login', 'login_handle' => 'taken-name'])->assertSessionHasErrors('login_handle');
    }

    public function test_the_business_link_dresses_the_sign_in_pages_only(): void
    {
        [$platform] = $this->makeBusiness(['business_name' => 'Platform Co']);
        System::setProperty('primary_business_id', $platform->id);
        [$business] = $this->makeBusiness(['business_name' => 'Kigali Sun']);
        $business->update(['brand_color' => '#0A7A4B']);
        $settings = app(BusinessSettings::class);
        $settings->set('branding.login_handle', 'kigali-sun', $business);
        $settings->set('branding.login_heading', 'Murakaza neza', $business);
        $settings->set('branding.slogan', 'Uniforms made in Kigali', $business);

        $this->get(route('staff.login'))->assertOk()->assertSee('Platform Co')->assertDontSee('Murakaza neza');

        $this->get(route('staff.business-login', 'kigali-sun'))->assertRedirect(route('staff.login'));
        $this->get(route('staff.login'))->assertOk()
            ->assertSee('Murakaza neza')->assertSee('Uniforms made in Kigali')
            ->assertSee('--brand: #0A7A4B', false)
            ->assertDontSee(route('staff.register'), false);
        $this->get(route('staff.password.request'))->assertOk()->assertSee('--brand: #0A7A4B', false);

        // Registration makes a new business: the platform's brand.
        $this->get(route('staff.register'))->assertDontSee('--brand: #0A7A4B', false);

        // An unknown link changes nothing.
        $this->flushSession();
        $this->get(route('staff.business-login', 'nobody-here'))->assertRedirect(route('staff.login'));
        $this->get(route('staff.login'))->assertDontSee('Murakaza neza');
    }

    public function test_the_brand_is_only_the_look_and_is_remembered_after_sign_in(): void
    {
        [$a] = $this->makeBusiness(['business_name' => 'Alpha']);
        [$b, $bOwner] = $this->makeBusiness(['business_name' => 'Beta']);
        app(BusinessSettings::class)->set('branding.login_handle', 'alpha', $a);

        // Beta's user signs in through Alpha's link: it still works (look only).
        $this->get(route('staff.business-login', 'alpha'));
        $this->post(route('staff.login.store'), ['email' => $bOwner->email, 'password' => self::TEST_PASSWORD])
            ->assertRedirect(route('dashboard'))
            ->assertCookie(LoginBranding::KEY, (string) $b->id);
        $this->assertAuthenticatedAs($bOwner, 'staff');
    }
}
