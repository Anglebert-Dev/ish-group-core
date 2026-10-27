<?php

namespace App\Console\Commands;

use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use Illuminate\Console\Command;

/**
 * CLI twin of /app/super-admin/modules.
 *
 *   php artisan core:module list
 *   php artisan core:module install Frontend
 *   php artisan core:module update Frontend
 *   php artisan core:module uninstall Frontend [--purge]
 *   php artisan core:module enable|disable Frontend
 */
class ModuleCommand extends Command
{
    protected $signature = 'core:module
        {action : list, install, update, uninstall, enable or disable}
        {module? : Module name, e.g. Frontend}
        {--purge : On uninstall, also roll back the module\'s migrations (drops its tables)}';

    protected $description = 'Install, update, uninstall, enable or disable a code module';

    public function handle(ModuleManager $modules, PermissionService $permissions): int
    {
        $action = $this->argument('action');

        if ($action === 'list') {
            $this->table(
                ['Module', 'Installed', 'Available', 'Status'],
                $modules->catalogue()->map(fn (array $module) => [
                    $module['name'],
                    $module['installed_version'] ?? '—',
                    $module['available_version'],
                    $module['active'] ? 'active' : ($module['installed'] ? 'disabled' : 'not installed'),
                ])->all(),
            );

            return self::SUCCESS;
        }

        $name = (string) $this->argument('module');

        if ($name === '') {
            $this->components->error('Give a module name.');

            return self::FAILURE;
        }

        $result = match ($action) {
            'install' => $modules->install($name),
            'update' => $modules->update($name),
            'uninstall' => $modules->uninstall($name, (bool) $this->option('purge')),
            'enable' => $modules->toggle($name, true),
            'disable' => $modules->toggle($name, false),
            default => ['status' => false, 'message' => "Unknown action [{$action}]."],
        };

        if (! $result['status']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $permissions->initPermissions(true);
        $this->components->info($result['message']);

        return self::SUCCESS;
    }
}
