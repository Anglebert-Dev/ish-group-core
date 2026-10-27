<?php

namespace App\Services\Permission;

use App\Services\Module\ModuleManager;

/**
 * The full permission catalogue: core config/access.php merged with every
 * active module's DataController::user_permissions() result, which uses the
 * same keys (access_modules, extra_permissions, module_labels,
 * controller_labels).
 */
class AccessCatalogue
{
    public function __construct(protected ModuleManager $modules)
    {
    }

    /**
     * @return array{access_modules: array<string, list<string>>, access_actions: list<string>, extra_permissions: list<string>, module_labels: array<string, string>, controller_labels: array<string, string>}
     */
    public function config(): array
    {
        $config = config('access');

        foreach ($this->modules->cachedHook('user_permissions') as $result) {
            if (! is_array($result)) {
                continue;
            }

            foreach ($result['access_modules'] ?? [] as $module => $controllers) {
                $config['access_modules'][$module] = array_values(array_unique(array_merge(
                    $config['access_modules'][$module] ?? [],
                    $controllers,
                )));
            }

            $config['extra_permissions'] = array_values(array_unique(array_merge(
                $config['extra_permissions'] ?? [],
                $result['extra_permissions'] ?? [],
            )));
            $config['module_labels'] = ($config['module_labels'] ?? []) + ($result['module_labels'] ?? []);
            $config['controller_labels'] = ($config['controller_labels'] ?? []) + ($result['controller_labels'] ?? []);
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $config = $this->config();
        $names = [];

        foreach ($config['access_modules'] ?? [] as $module => $controllers) {
            foreach ($controllers as $controller) {
                foreach ($config['access_actions'] ?? [] as $action) {
                    $names[] = "{$action} {$module}.{$controller}";
                }
            }
        }

        return array_values(array_unique(array_merge($names, $config['extra_permissions'] ?? [])));
    }
}
