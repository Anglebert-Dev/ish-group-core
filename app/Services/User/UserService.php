<?php

namespace App\Services\User;

use App\Enums\UserStatus;
use App\Libraries\ListExport;
use App\Models\User;
use App\Repositories\User\UserRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService extends BaseService
{
    private const EXPORT_ROW_LIMIT = 5000;

    public function __construct(protected UserRepository $users)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->users->query()->with('roles')->orderBy('name')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->users->query()
            ->with('roles')
            ->orderBy('name')
            ->limit(self::EXPORT_ROW_LIMIT)
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->roles->first()?->name ?? '—',
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
     * Staff roles assignable by the acting user (SuperAdmin only for SuperAdmin).
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function assignableRoles(User $actingUser)
    {
        $query = Role::where('guard_name', 'staff')->orderBy('name');

        if (! $actingUser->hasRole('SuperAdmin')) {
            $query->where('name', '!=', 'SuperAdmin');
        }

        return $query->get();
    }

    public function create(User $actingUser, array $data): array
    {
        if (! empty($data['role']) && ! $this->canAssignRole($actingUser, $data['role'])) {
            return $this->failure(__('body.cannot_assign_superadmin'));
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->status = UserStatus::Active;

        $this->users->save($user);

        if (! empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        return $this->success($user, __('body.staff_created'));
    }

    public function update(User $actingUser, User $user, array $data): array
    {
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $this->users->save($user);

        // SuperAdmin's role is fixed; the edit form disables the field entirely
        // (so it never submits), and we must not treat that as "clear the role".
        if (! $user->hasRole('SuperAdmin')) {
            $role = $data['role'] ?? null;

            if (! empty($role) && ! $this->canAssignRole($actingUser, $role)) {
                return $this->failure(__('body.cannot_assign_superadmin'));
            }

            $user->syncRoles(! empty($role) ? [$role] : []);
        }

        return $this->success($user, __('body.staff_updated'));
    }

    public function updateStatus(User $actingUser, User $target, UserStatus $status): array
    {
        if ($actingUser->is($target)) {
            return $this->failure(__('body.cannot_modify_self_status'));
        }

        if ($target->hasRole('SuperAdmin') && ! $actingUser->hasRole('SuperAdmin')) {
            return $this->failure(__('body.cannot_modify_superadmin'));
        }

        $target->status = $status;
        $this->users->save($target);

        return $this->success($target, __('body.staff_status_updated'));
    }

    private function canAssignRole(User $actingUser, string $role): bool
    {
        if ($role !== 'SuperAdmin') {
            return true;
        }

        return $actingUser->hasRole('SuperAdmin');
    }
}
