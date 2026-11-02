<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Services\Branding\ThemeService;
use Tests\TestCase;

/**
 * Dashboard, menu, landing page, announcement and presets
 * (docs/branding.md §3.7, 3.8, 3.14, phase B6).
 */
class DashboardMenuBrandingTest extends TestCase
{
    public function test_dashboard_blocks_follow_the_business_and_roles_can_only_hide(): void
    {
        $owner = $this->makeStaff();
        $member = $this->makeMember($owner->business, ['list users.user']);
        $themes = app(ThemeService::class);
        $themes->saveDraft($owner->business, ['dashboard' => [
            'welcome_title' => 'Good morning, :name',
            'order' => ['recent_activity', 'stats'],
            'hidden' => ['quick_actions'],
            'roles_hidden' => ['stats' => [$member->roles->first()->id]],
        ]]);
        $themes->publish($owner->business);

        $this->actingAs($owner, 'staff');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Good morning, '.$owner->name)
            ->assertSee(__('body.active_staff'))
            ->assertDontSee(__('body.quick_action_add_staff'));

        $this->flushSession();
        $this->actingAs($member, 'staff');
        $this->get(route('dashboard'))->assertOk()->assertDontSee(__('body.active_staff'));
    }

    public function test_the_menu_can_be_renamed_and_tidied_but_never_reveals_or_locks(): void
    {
        $owner = $this->actingAsStaff();
        $this->get(route('settings.index', ['tab' => 'menu']))->assertOk()->assertSee('menu[items][users.index][label]', false);

        $this->put(route('settings.update'), ['section' => 'menu', 'menu' => [
            'items' => ['users.index' => ['label' => 'Our team', 'icon' => 'scissors'], 'customers.index' => ['hidden' => '1']],
            'groups' => ['Management' => ['label' => 'Office']],
        ]])->assertSessionHasNoErrors();
        app(ThemeService::class)->publish($owner->business);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Our team')->assertSee('Office')
            ->assertDontSee('title="'.__('body.customers').'"', false);
        // Hidden from the menu, not locked: the page still opens for people allowed.
        $this->get(route('customers.index'))->assertOk();
    }

    public function test_the_landing_page_applies_only_to_those_who_may_open_it(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $member = $this->makeMember($business, ['list users.user']);
        app(ThemeService::class)->saveDraft($business, ['landing' => 'customers.index']);
        app(ThemeService::class)->publish($business);

        $this->post(route('staff.login.store'), ['email' => $owner->email, 'password' => self::TEST_PASSWORD])->assertRedirect(route('customers.index'));
        $this->post(route('staff.logout'));
        $this->post(route('staff.login.store'), ['email' => $member->email, 'password' => self::TEST_PASSWORD])->assertRedirect(route('dashboard'));
    }

    public function test_the_announcement_shows_between_its_dates(): void
    {
        $owner = $this->actingAsStaff();
        $themes = app(ThemeService::class);

        $themes->saveDraft($owner->business, ['announcement' => ['text' => 'Closed on Friday for stock count', 'tone' => 'warning', 'until' => today()->toDateString()]]);
        $themes->publish($owner->business);
        $this->get(route('customers.index'))->assertOk()->assertSee('Closed on Friday for stock count');

        $themes->saveDraft($owner->business, ['announcement' => ['text' => 'Old news', 'until' => today()->subDay()->toDateString()]]);
        $themes->publish($owner->business);
        $this->get(route('customers.index'))->assertOk()->assertDontSee('Old news');
    }

    public function test_presets_change_only_the_look(): void
    {
        $owner = $this->actingAsStaff();
        $themes = app(ThemeService::class);
        $themes->saveDraft($owner->business, ['dashboard' => ['welcome_title' => 'Keep me'], 'colors' => ['card' => '#FAFAFA']]);

        $this->put(route('settings.theme.presets.apply'), ['preset' => 'builtin:fashion'])->assertRedirect();
        $draft = $themes->draft($owner->business)->payload;
        $this->assertSame('playfair', $draft['heading_font']);
        $this->assertSame('#2B1B17', $draft['colors']['sidebar']);
        $this->assertArrayNotHasKey('card', $draft['colors'], 'the preset sets the whole colour set');
        $this->assertSame('Keep me', $draft['dashboard']['welcome_title'], 'content is left alone');

        $this->put(route('settings.theme.presets.save'), ['preset_name' => 'Our look'])->assertSessionHas('success');
        $this->put(route('settings.theme.presets.apply'), ['preset' => 'builtin:minimal']);
        $this->put(route('settings.theme.presets.apply'), ['preset' => 'saved:0'])->assertSessionHas('success');
        $this->assertSame('playfair', $themes->draft($owner->business)->payload['heading_font']);

        $this->put(route('settings.theme.presets.delete'), ['preset_index' => 0])->assertSessionHas('success');
        $this->assertSame([], $themes->savedPresets($owner->business));
        $this->get(route('settings.index', ['tab' => 'theme']))->assertOk()->assertSee(__('body.preset_fashion'));
    }

    public function test_the_platform_passes_on_its_look_but_not_its_dashboard_or_menu(): void
    {
        [$platform] = $this->makeBusiness();
        System::setProperty('primary_business_id', $platform->id);
        $themes = app(ThemeService::class);
        $themes->saveDraft($platform, ['layout' => ['corners' => 'sharp'], 'menu' => ['items' => ['users.index' => ['label' => 'Platform team']]], 'dashboard' => ['welcome_title' => 'Platform hello']]);
        $themes->publish($platform);

        [$tenant] = $this->makeBusiness();
        $theme = $themes->effective($tenant);
        $this->assertSame('sharp', $theme['layout']['corners']);
        $this->assertArrayNotHasKey('menu', $theme);
        $this->assertArrayNotHasKey('dashboard', $theme);
    }
}
