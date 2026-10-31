<?php

namespace App\Services\Module;

use App\Models\System\System;
use App\Services\BaseService;
use App\Support\Tenancy\CurrentBusiness;
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
 *   - it is switched on ("<alias>_enabled" in the system table; on unless set to "0")
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

    /** @var list<string> lower-case aliases switched off */
    private array $disabled = [];

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

        return $module !== null && $this->isEnabled($name) && $this->isInstalled($name);
    }

    /**
     * @return list<string> studly names of active modules
     */
    public function activeModules(): array
    {
        return collect(Module::all())
            ->filter(fn (ModuleInstance $module) => $this->isInstalled($module->getName()) && $this->isEnabled($module->getName()))
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

        if ($missing = $this->missingRequirements($name)) {
            return $this->failure(__('body.module_requires', ['name' => $module->getName(), 'modules' => implode(', ', $missing)]));
        }

        $this->setEnabled($module, true);
        $this->migrate($module);
        System::addProperty($this->versionKey($module), $this->availableVersion($module));
        $this->flush();
        $this->audit('installed', $module);

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
        $this->audit('updated', $module);

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

        if ($dependents = $this->dependents($name)) {
            return $this->failure(__('body.module_required_by', ['name' => $module->getName(), 'modules' => implode(', ', $dependents)]));
        }

        if ($purgeData && is_dir($this->migrationPath($module))) {
            Artisan::call('migrate:reset', [
                '--path' => $this->migrationPath($module),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        System::removeProperty($this->versionKey($module));
        System::removeProperty($module->getLowerName().'_enabled');
        $this->flush();
        $this->audit($purgeData ? 'purged' : 'uninstalled', $module);

        return $this->success($module->getName(), __('body.module_uninstalled', ['name' => $module->getName()]));
    }

    /**
     * Code-level on/off for an installed module, keeping its data.
     *
     * One switch for a whole family: turning a module off also turns off
     * every module that needs it (Inventory off = Sales, Production,
     * Invoicing… off); turning one on also turns on what it needs.
     */
    public function toggle(string $name, bool $enabled): array
    {
        $module = Module::find($name);

        if (! $module || ! $this->isInstalled($name)) {
            return $this->failure(__('body.module_not_installed'));
        }

        $affected = $enabled ? $this->requirementsToEnable($module->getName()) : $this->allDependents($module->getName());

        if ($enabled && ($missing = array_filter($affected, fn (string $required) => ! $this->isInstalled($required)))) {
            return $this->failure(__('body.module_requires', ['name' => $module->getName(), 'modules' => implode(', ', $missing)]));
        }

        foreach ([...$affected, $module->getName()] as $target) {
            $instance = Module::find($target);
            $this->setEnabled($instance, $enabled);
            $this->flush();
            $this->audit($enabled ? 'enabled' : 'disabled', $instance);
        }

        $message = $enabled
            ? __('body.module_enabled', ['name' => $module->getName()])
            : __('body.module_disabled', ['name' => $module->getName()]);

        if ($affected !== []) {
            $message .= ' '.__($enabled ? 'body.module_also_enabled' : 'body.module_also_disabled', ['modules' => implode(', ', $affected)]);
        }

        return $this->success($module->getName(), $message);
    }

    /**
     * Active modules that need $name, directly or through another module.
     *
     * @return list<string>
     */
    public function allDependents(string $name): array
    {
        $found = [];
        $queue = [$name];

        while ($queue !== []) {
            foreach ($this->dependents(array_shift($queue)) as $dependent) {
                if (! in_array($dependent, $found, true)) {
                    $found[] = $dependent;
                    $queue[] = $dependent;
                }
            }
        }

        return $found;
    }

    /**
     * Requirements (all the way down) that are not enabled yet.
     *
     * @return list<string>
     */
    private function requirementsToEnable(string $name): array
    {
        return array_values(array_filter(
            array_diff($this->withRequirements([$name]), [$name]),
            fn (string $required) => ! $this->isEnabled($required),
        ));
    }

    /**
     * Call a hook on every active module. Returns [ModuleName => result].
     *
     * @return array<string, mixed>
     */
    public function hook(string $hook, mixed $arguments = null): array
    {
        $results = [];
        $business = app(CurrentBusiness::class)->get();

        foreach ($this->activeModules() as $name) {
            // Modules switched off for this business, or outside its package, stay silent.
            if ($business && ! $business->canUseModule($name)) {
                continue;
            }

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

   
    /**
     * module.json "billing_exempt": true — always available, never sold
     * (e.g. the billing module itself, so a locked business can still pay).
     */
    /**
     * Modules this one needs, from "requires" in its module.json.
     *
     * @return list<string>
     */
    public function requires(string $name): array
    {
        return collect((array) Module::find($name)?->get('requires', []))
            ->map(fn ($required) => Module::find((string) $required)?->getName() ?? (string) $required)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Required modules that are not active yet.
     *
     * @return list<string>
     */
    public function missingRequirements(string $name): array
    {
        return array_values(array_filter($this->requires($name), fn (string $required) => ! $this->isActive($required)));
    }

    /**
     * Active modules that need this one.
     *
     * @return list<string>
     */
    public function dependents(string $name): array
    {
        return collect($this->activeModules())
            ->filter(fn (string $other) => in_array(strtolower($name), array_map('strtolower', $this->requires($other)), true))
            ->values()
            ->all();
    }

    /**
     * $names plus everything they require, all the way down.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function withRequirements(array $names): array
    {
        $all = [];
        $queue = array_values($names);

        while ($queue !== []) {
            $name = array_shift($queue);

            if (in_array($name, $all, true)) {
                continue;
            }

            $all[] = $name;
            array_push($queue, ...$this->requires($name));
        }

        return $all;
    }

    public function isBillingExempt(string $name): bool
    {
        return (bool) Module::find($name)?->get('billing_exempt', false);
    }

    public function menuGroup(string $name): string
    {
        return (string) (Module::find($name)?->get('menu_group') ?: $name);
    }

    public function installedVersion(string $name): ?string
    {
        return $this->installedVersions()[strtolower($name)] ?? null;
    }

    /**
     * Switched on? Stored as "<alias>_enabled" in the system table; on unless set to "0".
     */
    public function isEnabled(string $name): bool
    {
        $this->installedVersions();

        return ! in_array(strtolower($name), $this->disabled, true);
    }

    private function setEnabled(ModuleInstance $module, bool $enabled): void
    {
        System::addProperty($module->getLowerName().'_enabled', $enabled ? '1' : '0');
        $this->flush();
    }

    /**
     * Forget the module list read from disk (nwidart keeps it for the whole
     * process): needed after a module folder was replaced by an update.
     */
    public function rescan(): void
    {
        (new \ReflectionProperty(\Nwidart\Modules\FileRepository::class, 'modules'))->setValue(null, []);
        $this->flush();
    }

    public function flush(): void
    {
        $this->installed = null;
        $this->disabled = [];
        $this->hookCache = [];
    }

    private function audit(string $event, ModuleInstance $module): void
    {
        app(\App\Services\ActivityLog\ActivityLogService::class)->log(
            \App\Enums\ActivityLogAction::ModuleChanged,
            __('body.audit_log_module_'.$event, ['name' => $module->getName()]),
            null,
            ['module' => $module->getName(), 'event' => $event, 'version' => $this->availableVersion($module)],
        );
    }

    private function describe(ModuleInstance $module): array
    {
        $installedVersion = $this->installedVersion($module->getName());
        $available = $this->availableVersion($module);

        return [
            'name' => $module->getName(),
            'alias' => $module->getLowerName(),
            'description' => (string) $module->getDescription(),
            'requires' => $this->requires($module->getName()),
            'needed_by' => $installedVersion !== null && $this->isEnabled($module->getName()) ? $this->allDependents($module->getName()) : [],
            'enabled' => $this->isEnabled($module->getName()),
            'installed' => $installedVersion !== null,
            'active' => $installedVersion !== null && $this->isEnabled($module->getName()),
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

        $properties = System::query()->pluck('value', 'key');

        $this->disabled = $properties
            ->filter(fn ($value, $key) => str_ends_with($key, '_enabled') && (string) $value === '0')
            ->keys()->map(fn ($key) => substr($key, 0, -strlen('_enabled')))->values()->all();

        return $this->installed = $properties
            ->filter(fn ($version, $key) => str_ends_with($key, '_version'))
            ->mapWithKeys(fn ($version, $key) => [substr($key, 0, -strlen('_version')) => (string) $version])
            ->all();
    }
}
