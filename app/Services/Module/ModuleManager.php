<?php

namespace App\Services\Module;

use App\Models\System\System;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as ModuleInstance;
use Throwable;

/**
 * Module registry + hook bus (reference doc §1a, §1b).
 *
 * A module is ACTIVE only when both hold:
 *   - its code is enabled (nwidart, modules_statuses.json)
 *   - it is installed: the system table has a "<lowername>_version" row
 *
 * Hooks: core calls hook('name', $args) at its extension points; every active
 * module whose Modules\<Name>\Http\Controllers\DataController has a method of
 * that name answers. Results are keyed by module name.
 */
class ModuleManager extends BaseService
{
    /** @var array<string, string>|null lowername => installed version */
    private ?array $installed = null;

    /** @var array<string, array<string, mixed>> */
    private array $hookCache = [];

    /**
     * @return Collection<int, array{name: string, alias: string, description: string, enabled: bool, installed: bool, active: bool, installed_version: ?string, available_version: string, update_available: bool}>
     */
    public function catalogue(): Collection
    {
        return collect(Module::all())
            ->map(fn (ModuleInstance $module) => $this->describe($module))
            ->sortBy('name')
            ->values();
    }

    public function exists(string $name): bool
    {
        return Module::find($name) !== null;
    }

    public function isInstalled(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->installedVersions());
    }

    public function isActive(string $name): bool
    {
        $module = Module::find($name);

        return $module !== null && $module->isEnabled() && $this->isInstalled($name);
    }

    /**
     * @return list<string> studly names of active modules
     */
    public function activeModules(): array
    {
        return collect(Module::allEnabled())
            ->filter(fn (ModuleInstance $module) => $this->isInstalled($module->getName()))
            ->map(fn (ModuleInstance $module) => $module->getName())
            ->values()
            ->all();
    }

    public function install(string $name): array
    {
        $module = Module::find($name);

        if (! $module) {
            return $this->failure(__('body.module_not_found'));
        }

        if ($this->isInstalled($name)) {
            return $this->failure(__('body.module_already_installed'));
        }

        $module->enable();
        $this->migrate($module);
        System::addProperty($this->versionKey($module), $this->availableVersion($module));
        $this->flush();

        return $this->success($module->getName(), __('body.module_installed', ['name' => $module->getName()]));
    }

    public function update(string $name): array
    {
        $module = Module::find($name);

        if (! $module || ! $this->isInstalled($name)) {
            return $this->failure(__('body.module_not_installed'));
        }

        if (! version_compare($this->availableVersion($module), (string) $this->installedVersion($name), '>')) {
            return $this->failure(__('body.module_up_to_date'));
        }

        $this->migrate($module);
        System::setProperty($this->versionKey($module), $this->availableVersion($module));
        $this->flush();

        return $this->success($module->getName(), __('body.module_updated', ['name' => $module->getName()]));
    }

    /**
     * Unlike the reference implementation (pitfall 6), $purgeData rolls back
     * the module's own migrations so its tables don't linger.
     */
    public function uninstall(string $name, bool $purgeData = false): array
    {
        $module = Module::find($name);

        if (! $module || ! $this->isInstalled($name)) {
            return $this->failure(__('body.module_not_installed'));
        }

        if ($purgeData && is_dir($this->migrationPath($module))) {
            Artisan::call('migrate:reset', [
                '--path' => $this->migrationPath($module),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        System::removeProperty($this->versionKey($module));
        $module->disable();
        $this->flush();

        return $this->success($module->getName(), __('body.module_uninstalled', ['name' => $module->getName()]));
    }

    /**
     * Code-level on/off for an installed module, keeping its data.
     */
    public function toggle(string $name, bool $enabled): array
    {
        $module = Module::find($name);

        if (! $module || ! $this->isInstalled($name)) {
            return $this->failure(__('body.module_not_installed'));
        }

        $enabled ? $module->enable() : $module->disable();
        $this->flush();

        return $this->success($module->getName(), $enabled
            ? __('body.module_enabled', ['name' => $module->getName()])
            : __('body.module_disabled', ['name' => $module->getName()]));
    }

    /**
     * Call a hook on every active module. Returns [ModuleName => result].
     *
     * @return array<string, mixed>
     */
    public function hook(string $hook, mixed $arguments = null): array
    {
        $results = [];

        foreach ($this->activeModules() as $name) {
            $class = "Modules\\{$name}\\Http\\Controllers\\DataController";

            if (! class_exists($class) || ! method_exists($class, $hook)) {
                continue;
            }

            try {
                $results[$name] = app($class)->{$hook}($arguments);
            } catch (Throwable $e) {
                // One broken module must not take the whole app down.
                report($e);
            }
        }

        return $results;
    }

    /**
     * hook() for side-effect-free hooks that are safe to memoise for the
     * rest of the request (menu items, permission lists, ...).
     *
     * @return array<string, mixed>
     */
    public function cachedHook(string $hook): array
    {
        return $this->hookCache[$hook] ??= $this->hook($hook);
    }

    /**
     * Flattened list results of a hook, e.g. every module's menu items.
     *
     * @return list<mixed>
     */
    public function collectHook(string $hook): array
    {
        return collect($this->cachedHook($hook))
            ->filter(fn ($result) => is_array($result))
            ->flatten(1)
            ->values()
            ->all();
    }

    public function installedVersion(string $name): ?string
    {
        return $this->installedVersions()[strtolower($name)] ?? null;
    }

    public function flush(): void
    {
        $this->installed = null;
        $this->hookCache = [];
    }

    private function describe(ModuleInstance $module): array
    {
        $installedVersion = $this->installedVersion($module->getName());
        $available = $this->availableVersion($module);

        return [
            'name' => $module->getName(),
            'alias' => $module->getLowerName(),
            'description' => (string) $module->getDescription(),
            'enabled' => $module->isEnabled(),
            'installed' => $installedVersion !== null,
            'active' => $installedVersion !== null && $module->isEnabled(),
            'installed_version' => $installedVersion,
            'available_version' => $available,
            'update_available' => $installedVersion !== null && version_compare($available, $installedVersion, '>'),
        ];
    }

    private function migrate(ModuleInstance $module): void
    {
        if (! is_dir($this->migrationPath($module))) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $this->migrationPath($module),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function migrationPath(ModuleInstance $module): string
    {
        return $module->getExtraPath(config('modules.paths.generator.migration.path', 'database/migrations'));
    }

    private function availableVersion(ModuleInstance $module): string
    {
        return (string) $module->get('version', '1.0.0');
    }

    private function versionKey(ModuleInstance $module): string
    {
        return $module->getLowerName().'_version';
    }

    /**
     * @return array<string, string>
     */
    private function installedVersions(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        if (! Schema::hasTable('system')) {
            return $this->installed = [];
        }

        return $this->installed = System::query()
            ->pluck('value', 'key')
            ->filter(fn ($version, $key) => str_ends_with($key, '_version'))
            ->mapWithKeys(fn ($version, $key) => [substr($key, 0, -strlen('_version')) => (string) $version])
            ->all();
    }
}
