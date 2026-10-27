<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\User;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    public function test_root_goes_straight_to_staff_login_without_a_public_site(): void
    {
        $this->get('/')->assertRedirect(route('staff.login'));
    }

    public function test_root_sends_signed_in_staff_to_the_dashboard(): void
    {
        $this->actingAsStaff();

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_first_registration_creates_business_and_superadmin_owner(): void
    {
        $this->get(route('staff.register'))->assertOk();

        $this->post(route('staff.register.store'), [
            'business_name' => 'Acme Ltd',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $business = Business::query()->where('name', 'Acme Ltd')->firstOrFail();

        $this->assertAuthenticatedAs($user, 'staff');
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isBusinessAdmin());
        $this->assertSame($business->id, $user->business_id);
        $this->assertSame($user->id, $business->owner_id);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame((string) $business->id, System::getProperty('primary_business_id'));
    }

    public function test_registration_is_closed_after_bootstrap_unless_enabled(): void
    {
        $this->makeSuperAdmin();

        $this->get(route('staff.register'))->assertRedirect(route('staff.login'));
        $this->post(route('staff.register.store'), [
            'business_name' => 'Second Co',
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('staff.login'));

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_open_registration_creates_a_regular_business_admin(): void
    {
        $this->makeSuperAdmin();
        System::addProperty('allow_registration', '1');

        $this->post(route('staff.register.store'), [
            'business_name' => 'Second Co',
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'second@example.com')->firstOrFail();
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isBusinessAdmin());
    }

    public function test_registration_can_require_superadmin_activation(): void
    {
        $this->makeSuperAdmin();
        System::addProperty('allow_registration', '1');
        System::addProperty('default_business_active_status', '0');

        $this->post(route('staff.register.store'), [
            'business_name' => 'Pending Co',
            'name' => 'Pending',
            'email' => 'pending-owner@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('staff.login'));

        $this->assertGuest('staff');
        $this->assertFalse(Business::query()->where('name', 'Pending Co')->value('is_active'));
    }

    public function test_active_staff_can_log_in_and_reach_the_dashboard(): void
    {
        $this->makeStaff(['email' => 'login@example.com']);

        $this->post(route('staff.login.store'), [
            'email' => 'login@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_staff_remember_me_sets_a_recaller_cookie(): void
    {
        $this->makeStaff(['email' => 'remember@example.com']);

        $this->post(route('staff.login.store'), [
            'email' => 'remember@example.com',
            'password' => self::TEST_PASSWORD,
            'remember' => '1',
        ])->assertRedirect(route('dashboard'))
            ->assertCookie(auth()->guard('staff')->getRecallerName());
    }

    public function test_pending_staff_cannot_log_in(): void
    {
        $this->makeStaff(['email' => 'pending@example.com', 'status' => UserStatus::Pending]);

        $this->post(route('staff.login.store'), [
            'email' => 'pending@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest('staff');
    }

    public function test_staff_of_an_inactive_business_cannot_log_in(): void
    {
        [$business] = $this->makeBusiness(['email' => 'inactive@example.com']);
        $business->forceFill(['is_active' => false])->save();

        $this->post(route('staff.login.store'), [
            'email' => 'inactive@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest('staff');
    }

    public function test_deactivating_a_business_ends_live_sessions_on_next_request(): void
    {
        $user = $this->actingAsStaff();
        $this->get(route('dashboard'))->assertOk();

        $user->business->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }

    public function test_guest_cannot_open_staff_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
    }

    public function test_app_root_redirects_by_auth_state(): void
    {
        $this->get('/app')->assertRedirect(route('staff.login'));

        $this->actingAsStaff();
        $this->get('/app')->assertRedirect(route('dashboard'));
    }

    public function test_staff_can_log_out(): void
    {
        $this->actingAsStaff();

        $this->post(route('staff.logout'))->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeStaff(['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('staff.login.store'), ['email' => 'throttle@example.com', 'password' => 'WrongPass1!']);
        }

        $this->post(route('staff.login.store'), ['email' => 'throttle@example.com', 'password' => 'WrongPass1!'])
            ->assertStatus(429);
    }

    public function test_weak_password_is_rejected_on_registration(): void
    {
        $this->post(route('staff.register.store'), [
            'business_name' => 'Weak Co',
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }
}
