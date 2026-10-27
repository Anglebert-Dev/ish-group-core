<?php

namespace Tests\Feature;

use App\Services\SuperAdmin\ImpersonationService;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    private function loginAsSuperAdmin(): \App\Models\User
    {
        $super = $this->makeSuperAdmin(['email' => 'root@example.com']);
        $this->post(route('staff.login.store'), ['email' => 'root@example.com', 'password' => self::TEST_PASSWORD]);
        $this->assertAuthenticatedAs($super, 'staff');

        return $super;
    }

    public function test_superadmin_logs_in_as_a_user_and_returns(): void
    {
        $super = $this->loginAsSuperAdmin();
        [$business, $owner] = $this->makeBusiness(['name' => 'Client Owner']);

        $this->withConfirmedPassword()
            ->post(route('superadmin.impersonate', $owner->uuid))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner, 'staff');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('body.impersonation_return'))
            ->assertSee('Client Owner');

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('superadmin.businesses.show', $business));

        $this->assertAuthenticatedAs($super, 'staff');
        $this->assertNull(session(ImpersonationService::SESSION_KEY));
    }

    public function test_return_only_goes_back_to_the_stored_superadmin(): void
    {
        $super = $this->loginAsSuperAdmin();
        [, $owner] = $this->makeBusiness();
        [, $someoneElse] = $this->makeBusiness();

        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $owner->uuid));

        // Tampering with the target is impossible: there is no target to pass,
        // and starting another impersonation is refused.
        $this->withConfirmedPassword()
            ->post(route('superadmin.impersonate', $someoneElse->uuid));
        $this->assertAuthenticatedAs($owner, 'staff');

        $this->post(route('impersonation.leave'));
        $this->assertAuthenticatedAs($super, 'staff');
    }

    public function test_impersonated_user_cannot_reach_platform_pages_or_change_security(): void
    {
        $this->loginAsSuperAdmin();
        [, $owner] = $this->makeBusiness();

        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $owner->uuid));

        $this->get(route('superadmin.dashboard'))->assertForbidden();
        $this->put(route('staff.profile.password'), [
            'current_password' => self::TEST_PASSWORD,
            'password' => 'AnotherPass1!x',
            'password_confirmation' => 'AnotherPass1!x',
        ])->assertSessionHas('error', __('body.impersonation_blocked_action'));
    }

    public function test_cannot_impersonate_superadmins_or_self(): void
    {
        $super = $this->loginAsSuperAdmin();
        $otherSuper = $this->makeSuperAdmin();

        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $otherSuper->uuid))->assertSessionHas('error');
        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $super->uuid))->assertSessionHas('error');

        $this->assertAuthenticatedAs($super, 'staff');
    }

    public function test_business_admin_cannot_impersonate(): void
    {
        $this->actingAsStaff();
        [, $target] = $this->makeBusiness();

        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $target->uuid))->assertForbidden();
    }

    public function test_leave_without_impersonating_signs_out(): void
    {
        $this->actingAsStaff();

        $this->post(route('impersonation.leave'))->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }
}
