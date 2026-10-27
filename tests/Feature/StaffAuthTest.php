<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    public function test_first_staff_user_can_register_as_super_admin(): void
    {
        $this->get(route('staff.register'))->assertOk();

        $this->post(route('staff.register.store'), [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'admin@example.com')->first(), 'staff');
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'status' => UserStatus::Active->value,
        ]);
        $this->assertTrue(User::query()->where('email', 'admin@example.com')->first()->hasRole('SuperAdmin'));
    }

    public function test_registration_closes_after_the_first_staff_user(): void
    {
        $this->makeStaff();

        $this->get(route('staff.register'))->assertRedirect(route('staff.login'));

        $this->post(route('staff.register.store'), [
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertRedirect(route('staff.login'));
    }

    public function test_active_staff_can_log_in_and_reach_the_dashboard(): void
    {
        $this->makeStaff(['email' => 'login@example.com']);

        $this->post(route('staff.login.store'), [
            'email' => 'login@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_staff_remember_me_sets_a_recaller_cookie(): void
    {
        $this->makeStaff(['email' => 'remember@example.com']);

        $response = $this->post(route('staff.login.store'), [
            'email' => 'remember@example.com',
            'password' => self::TEST_PASSWORD,
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated('staff');
        $this->assertNotEmpty(
            User::query()->where('email', 'remember@example.com')->value('remember_token')
        );
        $response->assertCookie(auth()->guard('staff')->getRecallerName());
    }

    public function test_staff_login_without_remember_me_does_not_set_a_recaller_cookie(): void
    {
        $this->makeStaff(['email' => 'norecall@example.com']);

        $this->post(route('staff.login.store'), [
            'email' => 'norecall@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertCookieMissing(auth()->guard('staff')->getRecallerName());
    }

    public function test_pending_staff_cannot_log_in(): void
    {
        $this->makeStaff([
            'email' => 'pending@example.com',
            'status' => UserStatus::Pending,
        ]);

        $this->post(route('staff.login.store'), [
            'email' => 'pending@example.com',
            'password' => 'SecurePass1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('staff');
    }

    public function test_guest_cannot_open_staff_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
    }

    public function test_app_root_redirects_guest_to_staff_login(): void
    {
        $this->get('/app')->assertRedirect(route('staff.login'));
        $this->get(route('staff.home'))->assertRedirect(route('staff.login'));
    }

    public function test_app_root_redirects_authenticated_staff_to_dashboard(): void
    {
        $this->actingAsStaff();

        $this->get('/app')->assertRedirect(route('dashboard'));
        $this->get(route('staff.home'))->assertRedirect(route('dashboard'));
    }

    public function test_staff_can_log_out(): void
    {
        $this->actingAsStaff();

        $this->post(route('staff.logout'))->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }
}
