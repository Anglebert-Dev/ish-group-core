<?php

namespace App\Services\Role;

use App\Models\System\System;
use App\Support\Access\PlatformAccess;
use App\Services\ActivityLog\ActivityLogService;
use App\Enums\ActivityLogAction;
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
        $hidden = $this->hiddenPermissionModules();

        foreach (array_diff_key($config['access_modules'] ?? [], array_flip($hidden)) as $module => $controllers) {
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

        $data['permissions'] = $this->allowedPermissions((int) $role->business_id, $data['permissions'] ?? []);
        $role->syncPermissions($data['permissions']);
        $this->logPermissions($role, [], $data['permissions']);

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

        $before = $role->permissions()->pluck('name')->all();
        $role->update(['name' => Role::scopedName($data['name'], (int) $role->business_id)]);
        $data['permissions'] = $this->allowedPermissions((int) $role->business_id, $data['permissions'] ?? []);
        $role->syncPermissions($data['permissions']);
        $this->logPermissions($role, $before, $data['permissions']);

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

    private function logPermissions(Role $role, array $before, array $after): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return;
        }

        app(ActivityLogService::class)->log(
            ActivityLogAction::PermissionsChanged,
            __('body.audit_log_permissions', ['role' => $role->display_name, 'added' => count($added), 'removed' => count($removed)]),
            $role,
            ['added' => $added, 'removed' => $removed],
            $role->business_id,
        );
    }

    private function isReservedName(string $name): bool
    {
        return strcasecmp(trim($name), Role::ADMIN) === 0 || str_contains($name, Role::SEPARATOR);
    }

    /**
     * Platform-only permission modules, hidden from every business except the operator's.
     *
     * @return list<string>
     */
    private function hiddenPermissionModules(?int $businessId = null): array
    {
        $access = app(PlatformAccess::class);
        $businessId ??= (int) auth('staff')->user()?->business_id;

        return $businessId === (int) System::getProperty('primary_business_id') ? [] : $access->platformModules();
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function allowedPermissions(int $businessId, array $permissions): array
    {
        $hidden = $this->hiddenPermissionModules($businessId);

        return array_values(array_filter($permissions, function (string $permission) use ($hidden) {
            return ! preg_match('/^[a-z_]+ ([a-z0-9_]+)\./', $permission, $m) || ! in_array($m[1], $hidden, true);
        }));
    }
}
