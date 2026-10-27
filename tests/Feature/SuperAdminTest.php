<?php

namespace Tests\Feature;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    public function test_superadmin_can_open_platform_pages(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.dashboard'))->assertOk();
        $this->get(route('superadmin.businesses.index'))->assertOk();
        $this->get(route('superadmin.businesses.create'))->assertOk();
        $this->get(route('superadmin.modules.index'))->assertOk()->assertSee('Frontend');
        $this->get(route('superadmin.settings.index'))->assertOk();
    }

    public function test_superadmin_sidebar_shows_platform_link_only_to_superadmins(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->get(route('dashboard'))->assertSee(route('superadmin.dashboard'), false);

        $this->actingAs($this->makeStaff(), 'staff');
        $this->get(route('dashboard'))->assertDontSee(route('superadmin.dashboard'), false);
    }

    public function test_platform_section_sits_at_the_top_of_the_sidebar(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.modules.index'))
            ->assertOk()
            ->assertSeeInOrder([
                route('superadmin.dashboard'),
                route('superadmin.businesses.index'),
                route('superadmin.modules.index'),
                route('superadmin.settings.index'),
                route('dashboard'),
                route('users.index'),
            ], false);
    }

    public function test_business_page_offers_log_in_as_and_delete(): void
    {
        $super = $this->makeSuperAdmin();
        [$client, $owner] = $this->makeBusiness();
        $this->actingAs($super, 'staff');

        $this->get(route('superadmin.businesses.show', $client))
            ->assertOk()
            ->assertSee(route('superadmin.impersonate', $owner->uuid), false)
            ->assertSee(route('superadmin.businesses.destroy', $client), false);

        // Own business: no delete, and no "Log in as" for the superadmin.
        $this->get(route('superadmin.businesses.show', $super->business))
            ->assertOk()
            ->assertDontSee(route('superadmin.businesses.destroy', $super->business), false)
            ->assertDontSee(route('superadmin.impersonate', $super->uuid), false);
    }

    public function test_superadmin_creates_a_business_with_owner(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->withConfirmedPassword()->post(route('superadmin.businesses.store'), [
            'business_name' => 'Client Co',
            'name' => 'Client Owner',
            'email' => 'client@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
            'is_active' => '1',
        ])->assertRedirect();

        $business = Business::query()->where('name', 'Client Co')->firstOrFail();
        $owner = User::query()->where('email', 'client@example.com')->firstOrFail();

        $this->assertSame($business->id, $owner->business_id);
        $this->assertTrue($owner->isBusinessAdmin());
        $this->assertFalse($owner->isSuperAdmin());
    }

    public function test_superadmin_can_deactivate_another_business_but_not_own(): void
    {
        $super = $this->makeSuperAdmin();
        [$client] = $this->makeBusiness();
        $this->actingAs($super, 'staff');

        $this->withConfirmedPassword()
            ->put(route('superadmin.businesses.active', $client), ['is_active' => 0])
            ->assertSessionHas('success');
        $this->assertFalse($client->fresh()->is_active);

        $this->withConfirmedPassword()
            ->put(route('superadmin.businesses.active', $super->business), ['is_active' => 0])
            ->assertSessionHas('error');
        $this->assertTrue($super->business->fresh()->is_active);
    }

    public function test_superadmin_resets_owner_password(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        [$client, $owner] = $this->makeBusiness();

        $this->withConfirmedPassword()->put(route('superadmin.businesses.owner-password', $client), [
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('BrandNewPass1!', $owner->fresh()->password));
    }

    public function test_superadmin_toggles_registration(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->put(route('superadmin.settings.update'), [
            'allow_registration' => '1',
            'default_business_active_status' => '1',
        ])->assertRedirect(route('superadmin.settings.index'));

        $this->assertSame('1', System::getProperty('allow_registration'));
    }

    public function test_install_command_bootstraps_a_superadmin(): void
    {
        $this->artisan('core:install', [
            '--business' => 'Installed Co',
            '--name' => 'Root',
            '--email' => 'root@example.com',
            '--password' => self::TEST_PASSWORD,
        ])->assertSuccessful();

        $root = User::query()->where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($root->isSuperAdmin());
        $this->assertTrue($root->isBusinessAdmin());
    }
}
