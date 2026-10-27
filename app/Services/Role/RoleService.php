<?php

namespace App\Services\Role;

use App\Libraries\ListExport;
use App\Repositories\Role\RoleRepository;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService
{
    public function __construct(protected RoleRepository $roles)
    {
    }

    public function list(): Collection
    {
        return $this->roles->allForStaffGuard();
    }

    public function exportList(): ListExport
    {
        $rows = $this->list()
            ->map(fn (Role $role) => [
                $role->name,
                (string) $role->permissions_count.' '.__('body.permissions'),
            ])
            ->all();

        return ListExport::make(__('body.roles'), 'roles', [
            __('body.role_name'),
            __('body.permissions'),
        ], $rows);
    }

    public function find(int $id): ?Role
    {
        return $this->roles->find($id);
    }

    /**
     * Every permission this system knows about, in the same shape used to
     * seed them (see PermissionService), grouped by module for display.
     */
    public function permissionCatalogue(): array
    {
        $config = config('access');
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

    public function moduleLabel(string $module): string
    {
        return config("access.module_labels.{$module}")
            ?? str($module)->headline()->toString();
    }

    public function controllerLabel(string $controller): string
    {
        return config("access.controller_labels.{$controller}")
            ?? str($controller)->headline()->toString();
    }

    public function create(array $data): array
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'staff',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return $this->success($role, __('body.role_created'));
    }

    public function update(Role $role, array $data): array
    {
        if ($role->name === 'SuperAdmin') {
            return $this->failure(__('body.cannot_modify_superadmin'));
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return $this->success($role, __('body.role_updated'));
    }

    public function delete(Role $role): array
    {
        if ($role->name === 'SuperAdmin') {
            return $this->failure(__('body.cannot_modify_superadmin'));
        }

        $role->delete();

        return $this->success(null, __('body.role_deleted'));
    }
}
