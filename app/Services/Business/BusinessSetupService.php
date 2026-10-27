<?php

namespace App\Services\Business;

use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\Role\Role;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The one routine that creates a business (reference doc blueprint 10).
 * Used by self-registration, the superadmin panel and core:install, so a
 * business always gets the same default resources.
 */
class BusinessSetupService extends BaseService
{
    public function __construct(
        protected PermissionService $permissions,
        protected ModuleManager $modules,
    ) {
    }

    /**
     * @param  array{name: string, is_active?: bool, brand_color?: string, timezone?: string}  $businessData
     * @param  array{name: string, email: string, password: string, is_superadmin?: bool}  $ownerData
     * @return array{status: bool, data: array{business: Business, owner: User}|null, message: string}
     */
    public function createWithOwner(array $businessData, array $ownerData): array
    {
        $this->permissions->initPermissions();

        [$business, $owner] = DB::transaction(function () use ($businessData, $ownerData) {
            $business = Business::query()->create([
                'name' => $businessData['name'],
                'is_active' => $businessData['is_active'] ?? true,
                'brand_color' => $businessData['brand_color'] ?? Business::DEFAULT_BRAND_COLOR,
                'timezone' => $businessData['timezone'] ?? config('app.timezone'),
            ]);

            $owner = new User([
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'password' => Hash::make($ownerData['password']),
            ]);
            $owner->business_id = $business->id;
            $owner->is_superadmin = (bool) ($ownerData['is_superadmin'] ?? false);
            $owner->status = UserStatus::Active;
            $owner->save();

            $business->forceFill(['owner_id' => $owner->id])->save();

            $adminRole = $this->createDefaultRoles($business);
            $owner->assignRole($adminRole);

            return [$business, $owner];
        });

        // Outside the transaction: a module's seeding must not be able to roll
        // back the business itself.
        $this->modules->hook('after_business_created', ['business' => $business, 'owner' => $owner]);

        return $this->success(['business' => $business, 'owner' => $owner], __('body.business_created'));
    }

    /**
     * Admin#<id>: locked, no permissions attached — Gate::before grants it
     * everything inside its own business, including future module permissions.
     */
    protected function createDefaultRoles(Business $business): Role
    {
        return Role::query()->create([
            'name' => Role::scopedName(Role::ADMIN, $business->id),
            'guard_name' => 'staff',
            'business_id' => $business->id,
            'is_default' => true,
        ]);
    }
}
