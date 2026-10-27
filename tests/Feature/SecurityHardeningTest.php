<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_non_superadmin_cannot_assign_superadmin_role(): void
    {
        app(PermissionService::class)->initPermissions(true);

        $managerRole = Role::findOrCreate('Manager', 'staff');
        $managerRole->givePermissionTo('create users.user');

        $manager = new User([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make(self::TEST_PASSWORD),
        ]);
        $manager->status = UserStatus::Active;
        $manager->save();
        $manager->assignRole('Manager');

        $this->actingAs($manager, 'staff');
        $this->withSession(['auth.staff_password_confirmed_at' => time()]);

        $this->post(route('users.store'), [
            'name' => 'Escalator',
            'email' => 'escalator@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
            'role' => 'SuperAdmin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'escalator@example.com']);
    }

    public function test_non_superadmin_cannot_ban_superadmin(): void
    {
        app(PermissionService::class)->initPermissions(true);

        $managerRole = Role::findOrCreate('Manager', 'staff');
        $managerRole->givePermissionTo('update users.user');

        $manager = new User([
            'name' => 'Manager Two',
            'email' => 'manager2@example.com',
            'password' => Hash::make(self::TEST_PASSWORD),
        ]);
        $manager->status = UserStatus::Active;
        $manager->save();
        $manager->assignRole('Manager');

        $super = $this->makeStaff(['email' => 'super@example.com']);

        $this->actingAs($manager, 'staff');
        $this->withSession(['auth.staff_password_confirmed_at' => time()]);

        $this->put(route('users.status', $super), [
            'status' => UserStatus::Banned->value,
        ])->assertRedirect();

        $this->assertSame(UserStatus::Active, $super->fresh()->status);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeCustomer(['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'login' => 'throttle@example.com',
                'password' => 'WrongPass1!',
            ]);
        }

        $this->post(route('login.store'), [
            'login' => 'throttle@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(429);
    }

    public function test_customer_forgot_password_returns_generic_success(): void
    {
        $this->post(route('customer.password.email'), [
            'email' => 'nobody@example.com',
        ])->assertRedirect()->assertSessionHas('success', __('body.password_reset_link_sent'));
    }

    public function test_weak_password_is_rejected_on_registration(): void
    {
        $this->post(route('register.store'), [
            'account_type' => 'personal',
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_banned_customer_session_is_terminated(): void
    {
        $customer = $this->makeCustomer(['email' => 'active-then-ban@example.com']);
        $this->actingAsCustomer($customer);

        $customer->status = \App\Enums\CustomerStatus::Banned;
        $customer->save();

        $this->get(route('profile'))
            ->assertRedirect(route('login'));

        $this->assertGuest('customer');
    }
}
