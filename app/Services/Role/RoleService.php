<?php

namespace App\Services\Role;

use App\Libraries\ListExport;
use App\Models\Role\Role;
use App\Models\User;
use App\Repositories\Role\RoleRepository;
use App\Services\BaseService;
use App\Services\Permission\AccessCatalogue;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Collection;

/**
 * Roles of one business. Stored as "<Name>#<business_id>"; the default
 * Admin role is locked (Gate::before already grants it everything).
 */
class RoleService extends BaseService
{
    public function __construct(
        protected RoleRepository $roles,
        protected AccessCatalogue $catalogue,
        protected PermissionService $permissions,
    ) {
    }

    public function list(User $actingUser): Collection
    {
        return $this->roles->forBusiness($actingUser->business_id)
            ->withCount('permissions')
            ->get();
    }

    public function exportList(User $actingUser): ListExport
    {
        $rows = $this->list($actingUser)
            ->map(fn (Role $role) => [
                $role->display_name,
                (string) $role->permissions_count.' '.__('body.permissions'),
            ])
            ->all();

        return ListExport::make(__('body.roles'), 'roles', [
            __('body.role_name'),
            __('body.permissions'),
        ], $rows);
    }

    /**
     * Every permission the system knows about (core + active modules),
     * grouped by module for display.
     */
    public function permissionCatalogue(): array
    {
        $this->permissions->initPermissions();

        $config = $this->catalogue->config();
        $groups = [];

        foreach ($config['access_modules'] ?? [] as $module => $controllers) {
            foreach ($controllers as $controller) {
                foreach ($config['access_actions'] ?? [] as $action) {
                    $groups[$module][$controller][] = "{$action} {$module}.{$controller}";
                }
            }
        }

        return [
            'modules' => $groups,
            'extra' => $config['extra_permissions'] ?? [],
            'module_labels' => $config['module_labels'] ?? [],
            'controller_labels' => $config['controller_labels'] ?? [],
        ];
    }

    public function nameTaken(User $actingUser, string $name, ?Role $ignore = null): bool
    {
        return $this->roles->forBusiness($actingUser->business_id)
            ->where('name', Role::scopedName($name, (int) $actingUser->business_id))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }

    public function create(User $actingUser, array $data): array
    {
        if ($this->isReservedName($data['name']) || $this->nameTaken($actingUser, $data['name'])) {
            return $this->failure(__('body.role_name_taken'));
        }

        $role = Role::query()->create([
            'name' => Role::scopedName($data['name'], (int) $actingUser->business_id),
            'guard_name' => 'staff',
            'business_id' => $actingUser->business_id,
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return $this->success($role, __('body.role_created'));
    }

    public function update(User $actingUser, Role $role, array $data): array
    {
        if ($role->is_default) {
            return $this->failure(__('body.cannot_modify_default_role'));
        }

        if ($this->isReservedName($data['name']) || $this->nameTaken($actingUser, $data['name'], $role)) {
            return $this->failure(__('body.role_name_taken'));
        }

        $role->update(['name' => Role::scopedName($data['name'], (int) $role->business_id)]);
        $role->syncPermissions($data['permissions'] ?? []);

        return $this->success($role, __('body.role_updated'));
    }

    public function delete(Role $role): array
    {
        if ($role->is_default) {
            return $this->failure(__('body.cannot_modify_default_role'));
        }

        $role->delete();

        return $this->success(null, __('body.role_deleted'));
    }

    private function isReservedName(string $name): bool
    {
        return strcasecmp(trim($name), Role::ADMIN) === 0 || str_contains($name, Role::SEPARATOR);
    }
}
