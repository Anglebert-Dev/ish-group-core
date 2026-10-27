<?php

namespace Tests;

use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\Role\Role;
use App\Models\User;
use App\Services\Business\BusinessSetupService;
use App\Services\Permission\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const TEST_PASSWORD = 'SecurePass1!';

    private static int $seq = 0;

    protected function nextSeq(): int
    {
        return ++self::$seq;
    }

    /**
     * A business plus its owner, created exactly like registration does
     * (owner holds the locked Admin#<id> role).
     *
     * @return array{0: Business, 1: User}
     */
    protected function makeBusiness(array $overrides = []): array
    {
        $seq = $this->nextSeq();

        $result = app(BusinessSetupService::class)->createWithOwner(
            [
                'name' => $overrides['business_name'] ?? 'Business '.$seq,
                'is_active' => $overrides['is_active'] ?? true,
            ],
            [
                'name' => $overrides['name'] ?? 'Owner '.$seq,
                'email' => $overrides['email'] ?? "owner{$seq}@example.com",
                'password' => $overrides['password'] ?? self::TEST_PASSWORD,
                'is_superadmin' => $overrides['is_superadmin'] ?? false,
            ],
        );

        return [$result['data']['business'], $result['data']['owner']->fresh()];
    }

    /**
     * The owner (business Admin) of a fresh business.
     */
    protected function makeStaff(array $overrides = []): User
    {
        [, $owner] = $this->makeBusiness($overrides);

        if (isset($overrides['status'])) {
            $owner->status = $overrides['status'];
            $owner->save();
        }

        return $owner->fresh();
    }

    protected function makeSuperAdmin(array $overrides = []): User
    {
        return $this->makeStaff(['is_superadmin' => true] + $overrides);
    }

    /**
     * A non-admin member of $business holding a custom role with $permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function makeMember(Business $business, array $permissions = [], array $overrides = []): User
    {
        app(PermissionService::class)->initPermissions(true);

        $seq = $this->nextSeq();
        $role = Role::query()->create([
            'name' => Role::scopedName($overrides['role'] ?? 'Role '.$seq, $business->id),
            'guard_name' => 'staff',
            'business_id' => $business->id,
        ]);
        $role->syncPermissions($permissions);

        $user = new User([
            'name' => $overrides['name'] ?? 'Member '.$seq,
            'email' => $overrides['email'] ?? "member{$seq}@example.com",
            'password' => Hash::make($overrides['password'] ?? self::TEST_PASSWORD),
        ]);
        $user->business_id = $business->id;
        $user->status = $overrides['status'] ?? UserStatus::Active;
        $user->save();
        $user->assignRole($role);

        return $user->fresh();
    }

    protected function actingAsStaff(?User $user = null): User
    {
        $user ??= $this->makeStaff();

        $this->actingAs($user, 'staff');

        return $user;
    }

    /**
     * Satisfies the 'password.confirm.staff' middleware.
     */
    protected function withConfirmedPassword(): static
    {
        return $this->withSession(['auth.staff_password_confirmed_at' => time()]);
    }
}
