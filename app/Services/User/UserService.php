<?php

namespace App\Services\User;

use App\Enums\UserStatus;
use App\Libraries\ListExport;
use App\Models\Role\Role;
use App\Models\User;
use App\Repositories\Role\RoleRepository;
use App\Repositories\User\UserRepository;
use App\Services\BaseService;
use App\Support\Entitlements\Quota;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Staff users of the acting user's business. Rules (reference doc §3):
 * one role per user; only a business Admin may grant the Admin role; the
 * last Admin of a business can't be demoted or suspended.
 */
class UserService extends BaseService
{
    private const EXPORT_ROW_LIMIT = 5000;

    public function __construct(
        protected UserRepository $users,
        protected RoleRepository $roles,
    ) {
    }

    public function list(User $actingUser): LengthAwarePaginator
    {
        return $this->users->forBusiness($actingUser->business_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);
    }

    public function exportList(User $actingUser): ListExport
    {
        $rows = $this->users->forBusiness($actingUser->business_id)
            ->with('roles')
            ->orderBy('name')
            ->limit(self::EXPORT_ROW_LIMIT)
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->roleName() ?? '—',
                ucfirst($user->status->value),
            ])
            ->all();

        return ListExport::make(__('body.staff'), 'staff', [
            __('body.full_name'),
            __('body.email_address'),
            __('body.role'),
            __('body.status'),
        ], $rows);
    }

    /**
     * Roles of the acting user's business they may hand out. Non-admins
     * never see the Admin role.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actingUser): Collection
    {
        return $this->roles->forBusiness($actingUser->business_id)
            ->get()
            ->reject(fn (Role $role) => $role->isAdmin() && ! $actingUser->isBusinessAdmin())
            ->values();
    }

    public function create(User $actingUser, array $data): array
    {
        $role = $this->resolveRole($actingUser, $data['role'] ?? null);

        if ($role === false) {
            return $this->failure(__('body.cannot_assign_admin_role'));
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->business_id = $actingUser->business_id;
        $user->status = UserStatus::Active;

        $this->users->save($user);

        if ($role) {
            $user->assignRole($role);
        }

        return $this->success($user, __('body.staff_created'));
    }

    public function update(User $actingUser, User $user, array $data): array
    {
        $role = $this->resolveRole($actingUser, $data['role'] ?? null);

        if ($role === false) {
            return $this->failure(__('body.cannot_assign_admin_role'));
        }

        if ($user->isBusinessAdmin() && ! $role?->isAdmin() && $this->isLastAdmin($user)) {
            return $this->failure(__('body.cannot_remove_last_admin'));
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $this->users->save($user);
        $user->syncRoles($role ? [$role] : []);

        return $this->success($user, __('body.staff_updated'));
    }

    public function updateStatus(User $actingUser, User $target, UserStatus $status): array
    {
        if ($actingUser->is($target)) {
            return $this->failure(__('body.cannot_modify_self_status'));
        }

        if ($target->isBusinessAdmin() && ! $actingUser->isBusinessAdmin()) {
            return $this->failure(__('body.cannot_modify_admin'));
        }

        if ($status !== UserStatus::Active && $target->isBusinessAdmin() && $this->isLastAdmin($target)) {
            return $this->failure(__('body.cannot_remove_last_admin'));
        }

        if ($status === UserStatus::Active && $target->status !== UserStatus::Active
            && $target->business && ! app(Quota::class)->canAdd($target->business, 'users')) {
            return $this->failure(__('body.quota_reached', [
                'limit' => app(Quota::class)->limit($target->business, 'users'),
                'item' => __('body.quota_users'),
            ]));
        }

        $target->status = $status;
        $this->users->save($target);

        return $this->success($target, __('body.staff_status_updated'));
    }

    /**
     * @return Role|null|false null = no role, false = not allowed
     */
    private function resolveRole(User $actingUser, mixed $roleId): Role|null|false
    {
        if (empty($roleId)) {
            return null;
        }

        $role = $this->roles->forBusiness($actingUser->business_id)->find($roleId);

        if (! $role || ($role->isAdmin() && ! $actingUser->isBusinessAdmin())) {
            return false;
        }

        return $role;
    }

    private function isLastAdmin(User $user): bool
    {
        $adminRole = Role::scopedName(Role::ADMIN, (int) $user->business_id);

        return $this->users->forBusiness($user->business_id)
            ->role($adminRole)
            ->where('status', UserStatus::Active->value)
            ->count() <= 1;
    }
}
