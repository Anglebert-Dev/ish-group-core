<?php

namespace App\Services\SuperAdmin;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Repositories\Business\BusinessRepository;
use App\Repositories\User\UserRepository;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform-level operations for superadmins: businesses and platform
 * settings. Creating a business goes through BusinessSetupService.
 */
class PlatformService extends BaseService
{
    /** Platform settings in the system table, with defaults. */
    public const SETTINGS = [
        'allow_registration' => '0',
        'default_business_active_status' => '1',
    ];

    public function __construct(
        protected BusinessRepository $businesses,
        protected UserRepository $users,
        protected ModuleManager $modules,
    ) {
    }

    public function overview(): array
    {
        return [
            'businesses' => $this->businesses->query()->count(),
            'activeBusinesses' => $this->businesses->query()->where('is_active', true)->count(),
            'users' => $this->users->query()->count(),
            'activeModules' => count($this->modules->activeModules()),
            'recentBusinesses' => $this->businesses->query()->with('owner')->latest()->limit(5)->get(),
        ];
    }

    public function listBusinesses(): LengthAwarePaginator
    {
        return $this->businesses->paginateWithOwner();
    }

    public function businessUsers(Business $business): Collection
    {
        return $this->users->forBusiness($business->id)->with('roles')->orderBy('name')->get();
    }

    public function toggleActive(Business $business, bool $active, int $actingBusinessId): array
    {
        if (! $active && $business->id === $actingBusinessId) {
            return $this->failure(__('body.cannot_deactivate_own_business'));
        }

        $business->forceFill(['is_active' => $active])->save();

        return $this->success($business, $active ? __('body.business_activated') : __('body.business_deactivated'));
    }

    /**
     * Per-business feature toggles (reference doc §1c). Only modules that
     * exist can be listed.
     *
     * @param  list<string>  $modules
     */
    public function updateEnabledModules(Business $business, array $modules): array
    {
        $known = $this->modules->catalogue()->pluck('name')->all();

        $business->forceFill([
            'enabled_modules' => array_values(array_intersect($known, $modules)),
        ])->save();

        return $this->success($business, __('body.business_modules_updated'));
    }

    public function resetOwnerPassword(Business $business, string $password): array
    {
        $owner = $business->owner;

        if (! $owner) {
            return $this->failure(__('body.business_has_no_owner'));
        }

        $owner->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))->where('user_id', $owner->id)->delete();

        return $this->success($owner, __('body.owner_password_reset'));
    }

    /**
     * @return array<string, string>
     */
    public function settings(): array
    {
        $stored = System::getProperties(array_keys(self::SETTINGS));

        return array_merge(self::SETTINGS, array_filter($stored, fn ($value) => $value !== null));
    }

    public function updateSettings(array $data): array
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            System::addProperty($key, ! empty($data[$key]) ? '1' : '0');
        }

        return $this->success(null, __('body.settings_updated'));
    }

}
