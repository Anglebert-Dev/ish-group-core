<?php

namespace App\Services\SuperAdmin;

use App\Enums\ActivityLogAction;
use App\Enums\ActivityLogStatus;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\Role\Role;
use App\Models\User;
use App\Repositories\Business\BusinessRepository;
use App\Repositories\User\UserRepository;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

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

    /**
     * @param  array{q?: ?string, status?: ?string}  $filters
     */
    public function listBusinesses(array $filters = []): LengthAwarePaginator
    {
        return $this->filteredBusinesses($filters)->paginate(15)->withQueryString();
    }

    public function exportBusinesses(array $filters = []): ListExport
    {
        $rows = $this->filteredBusinesses($filters)
            ->limit(5000)
            ->get()
            ->map(fn (Business $business) => [
                $business->name,
                $business->owner?->name ?? '—',
                $business->owner?->email ?? '—',
                (string) $business->users_count,
                $business->is_active ? __('body.active') : __('body.inactive'),
                GeneralLibrary::formatDateTime($business->created_at, 'd M Y'),
            ])
            ->all();

        return ListExport::make(__('body.businesses'), 'businesses', [
            __('body.business_name'),
            __('body.owner'),
            __('body.email_address'),
            __('body.users'),
            __('body.status'),
            __('body.created'),
        ], $rows);
    }

    private function filteredBusinesses(array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        return Business::query()
            ->with('owner')
            ->withCount('users')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('owner', fn (Builder $owner) => $owner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->latest();
    }

    /**
     * Figures for the business detail page.
     */
    public function businessStats(Business $business): array
    {
        return [
            'users' => $this->users->forBusiness($business->id)->count(),
            'active_users' => $this->users->forBusiness($business->id)->where('status', 'active')->count(),
            'roles' => Role::query()->where('business_id', $business->id)->count(),
            'last_login' => $this->users->forBusiness($business->id)->max('last_login_at'),
        ];
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

    /**
     * Set a new password for any staff user; their other sessions end.
     */
    public function resetUserPassword(User $user, string $password): array
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        return $this->success($user, __('body.user_password_reset', ['name' => $user->name]));
    }

    /**
     * Permanently delete a business and everything that cascades from it
     * (users, roles, audit log, module data with a business_id foreign key).
     * Modules holding data without such a key clean up in the
     * before_business_deleted hook.
     */
    public function deleteBusiness(Business $business, User $actingUser, string $confirmName): array
    {
        if ($business->id === $actingUser->business_id) {
            return $this->failure(__('body.cannot_delete_own_business'));
        }

        if ((string) $business->id === (string) System::getProperty('primary_business_id')) {
            return $this->failure(__('body.cannot_delete_primary_business'));
        }

        if ($this->users->forBusiness($business->id)->withTrashed()->where('is_superadmin', true)->exists()) {
            return $this->failure(__('body.cannot_delete_business_with_superadmin'));
        }

        if (trim($confirmName) !== $business->name) {
            return $this->failure(__('body.business_delete_name_mismatch'));
        }

        $this->modules->hook('before_business_deleted', ['business' => $business]);

        DB::transaction(function () use ($business, $actingUser) {
            $users = $this->users->forBusiness($business->id)->withTrashed()->get(['id', 'email']);
            $userIds = $users->pluck('id');

            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
            DB::table(config('permission.table_names.model_has_roles'))
                ->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->delete();
            DB::table(config('permission.table_names.model_has_permissions'))
                ->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->delete();

            // Query-level delete: no model events, so nothing tries to write an
            // activity row for the business being removed. Foreign keys cascade.
            Business::withTrashed()->whereKey($business->id)->forceDelete();

            ActivityLog::query()->create([
                'status' => ActivityLogStatus::Active,
                'business_id' => $actingUser->business_id,
                'user_id' => $actingUser->id,
                'actor_name' => $actingUser->name,
                'action' => ActivityLogAction::Deleted,
                'subject_type' => $business->getMorphClass(),
                'subject_id' => $business->id,
                'subject_label' => $business->name,
                'description' => __('body.business_deleted_log', ['name' => $business->name]),
                'properties' => ['attributes' => ['name' => $business->name, 'users' => $userIds->count()]],
                'ip_address' => request()->ip(),
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->success(null, __('body.business_deleted', ['name' => $business->name]));
    }

    /**
     * @return array<string, string>
     */
    public function settings(): array
    {
        $stored = System::getProperties(array_keys(self::SETTINGS));

        return array_merge(self::SETTINGS, array_filter($stored, fn ($value) => $value !== null));
    }

    /**
     * Read-only facts for Platform settings → System.
     *
     * @return array<string, string>
     */
    public function systemInfo(): array
    {
        return [
            __('body.app_name') => (string) config('app.name'),
            __('body.environment') => app()->environment(),
            __('body.timezone') => (string) config('app.timezone'),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            __('body.database') => DB::connection()->getDriverName(),
            __('body.active_modules') => implode(', ', $this->modules->activeModules()) ?: '—',
            __('body.mail_driver') => (string) config('mail.default'),
        ];
    }

    public function updateSettings(array $data): array
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            System::addProperty($key, ! empty($data[$key]) ? '1' : '0');
        }

        return $this->success(null, __('body.settings_updated'));
    }

}
