<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Role\Role;
use App\Models\User;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    public function test_business_admin_passes_every_permission_check(): void
    {
        $owner = $this->actingAsStaff();

        $this->assertTrue($owner->can('list users.user'));
        $this->assertTrue($owner->can('some module.permission_added_later'));
        $this->get(route('users.index'))->assertOk();
        $this->get(route('roles.index'))->assertOk();
    }

    public function test_business_admin_never_gets_platform_abilities(): void
    {
        $owner = $this->actingAsStaff();

        $this->assertFalse($owner->can('superadmin'));
        $this->assertFalse($owner->can('manage_modules'));
        $this->get(route('superadmin.dashboard'))->assertForbidden();
        $this->get(route('superadmin.modules.index'))->assertForbidden();
    }

    public function test_member_is_limited_to_role_permissions(): void
    {
        [$business] = $this->makeBusiness();
        $member = $this->makeMember($business, ['list users.user']);
        $this->actingAs($member, 'staff');

        $this->get(route('users.index'))->assertOk();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();
    }

    public function test_user_list_only_shows_own_business(): void
    {
        [, $ownerA] = $this->makeBusiness(['name' => 'Alice Owner']);
        [, $ownerB] = $this->makeBusiness(['name' => 'Bob Owner']);

        $this->actingAs($ownerA, 'staff');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Alice Owner')
            ->assertDontSee('Bob Owner');
    }

    public function test_cannot_open_or_edit_a_user_of_another_business(): void
    {
        [, $ownerA] = $this->makeBusiness();
        [, $ownerB] = $this->makeBusiness();

        $this->actingAs($ownerA, 'staff');

        $this->get(route('users.show', $ownerB))->assertNotFound();
        $this->withConfirmedPassword()
            ->put(route('users.status', $ownerB), ['status' => UserStatus::Banned->value])
            ->assertNotFound();

        $this->assertSame(UserStatus::Active, $ownerB->fresh()->status);
    }

    public function test_roles_are_scoped_and_named_per_business(): void
    {
        [$businessA, $ownerA] = $this->makeBusiness();
        [$businessB] = $this->makeBusiness();
        $foreignRole = Role::query()->where('business_id', $businessB->id)->firstOrFail();

        $this->actingAs($ownerA, 'staff');

        $this->withConfirmedPassword()->post(route('roles.store'), [
            'name' => 'Manager',
            'permissions' => ['list users.user'],
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('name', 'Manager#'.$businessA->id)->firstOrFail();
        $this->assertSame($businessA->id, $role->business_id);
        $this->assertSame('Manager', $role->display_name);

        $this->get(route('roles.show', $foreignRole))->assertNotFound();
    }

    public function test_default_admin_role_cannot_be_edited_or_deleted(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $admin = Role::query()->where('name', $business->adminRoleName())->firstOrFail();

        $this->actingAs($owner, 'staff');

        $this->withConfirmedPassword()
            ->delete(route('roles.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertNotNull($admin->fresh());
    }

    public function test_admin_role_name_is_reserved(): void
    {
        $this->actingAsStaff();

        $this->withConfirmedPassword()
            ->post(route('roles.store'), ['name' => 'admin'])
            ->assertSessionHasErrors('name');
    }

    public function test_member_cannot_grant_the_admin_role(): void
    {
        [$business] = $this->makeBusiness();
        $member = $this->makeMember($business, ['create users.user']);
        $adminRole = Role::query()->where('name', $business->adminRoleName())->firstOrFail();

        $this->actingAs($member, 'staff');

        $this->withConfirmedPassword()->post(route('users.store'), [
            'name' => 'Escalator',
            'email' => 'escalator@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
            'role' => $adminRole->id,
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'escalator@example.com']);
    }

    public function test_member_cannot_assign_a_role_from_another_business(): void
    {
        [, $ownerA] = $this->makeBusiness();
        [$businessB] = $this->makeBusiness();
        $foreignRole = Role::query()->where('business_id', $businessB->id)->firstOrFail();

        $this->actingAs($ownerA, 'staff');

        $this->withConfirmedPassword()->post(route('users.store'), [
            'name' => 'Cross',
            'email' => 'cross@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
            'role' => $foreignRole->id,
        ])->assertSessionHasErrors('role');
    }

    public function test_admin_creates_staff_inside_own_business(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $this->actingAs($owner, 'staff');

        $this->withConfirmedPassword()->post(route('users.store'), [
            'name' => 'New Staff',
            'email' => 'new-staff@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('users.index'));

        $this->assertSame($business->id, User::query()->where('email', 'new-staff@example.com')->value('business_id'));
    }

    public function test_last_admin_cannot_be_suspended(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $secondAdmin = $this->makeMember($business);
        $secondAdmin->syncRoles([$business->adminRoleName()]);

        // Two admins: suspending one is fine.
        $this->actingAs($secondAdmin, 'staff');
        $this->withConfirmedPassword()
            ->put(route('users.status', $owner), ['status' => UserStatus::Banned->value])
            ->assertSessionHas('success');

        // Re-activate, then make the owner the only active admin left.
        $owner->refresh()->forceFill(['status' => UserStatus::Active])->save();
        $secondAdmin->syncRoles([]);

        $this->flushSession();
        $this->actingAs($owner, 'staff');
        $this->withConfirmedPassword()
            ->put(route('users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => null])
            ->assertSessionHas('error');

        $this->assertTrue($owner->fresh()->isBusinessAdmin());
    }

    public function test_activity_log_is_scoped_to_the_business(): void
    {
        [, $ownerA] = $this->makeBusiness(['name' => 'Alpha Person']);
        [, $ownerB] = $this->makeBusiness(['name' => 'Bravo Person']);

        $this->actingAs($ownerA, 'staff');

        $this->assertTrue(ActivityLog::query()->get()->every(fn ($log) => $log->business_id === $ownerA->business_id));
        $this->get(route('activity-logs.index'))->assertOk()->assertDontSee('Bravo Person');
    }
}
