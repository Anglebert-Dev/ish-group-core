<?php

namespace App\Services\Module;

use App\Support\Printing\Printable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Throwable;

/**
 * What a module adds, read from the module itself (its hooks and migrations),
 * so its page on Super Admin → Modules can't go out of date. Works before the
 * module is installed.
 */
class ModuleDetails
{
    /**
     * @return array{pages: list<string>, permissions: list<string>, tables: list<string>, documents: list<string>}
     */
    public function adds(string $name): array
    {
        return [
            'pages' => $this->pages($name),
            'permissions' => $this->permissions($name),
            'tables' => $this->tables($name),
            'documents' => $this->documents($name),
        ];
    }

    /** Where the module lives: its Super Admin tab, else its first sidebar page. */
    public function openUrl(string $name): ?string
    {
        foreach ([...$this->answer($name, 'superadmin_menu'), ...$this->answer($name, 'modifyAdminMenu')] as $item) {
            if (is_array($item) && Route::has((string) ($item['route'] ?? ''))) {
                return route($item['route']);
            }
        }

        return null;
    }

    /** @return list<string> */
    private function pages(string $name): array
    {
        return collect([...$this->answer($name, 'superadmin_menu'), ...$this->answer($name, 'modifyAdminMenu')])
            ->filter(fn ($item) => is_array($item) && isset($item['label']))
            ->map(fn (array $item) => __((string) $item['label']))
            ->unique()->values()->all();
    }

    /** @return list<string> */
    private function permissions(string $name): array
    {
        $config = $this->answer($name, 'user_permissions');
        $labels = (array) ($config['controller_labels'] ?? []);

        return collect((array) ($config['access_modules'] ?? []))
            ->flatten()
            ->map(fn ($thing) => __($labels[$thing] ?? Str::headline((string) $thing)))
            ->merge(collect((array) ($config['extra_permissions'] ?? []))->map(fn ($permission) => __(Str::headline((string) $permission))))
            ->unique()->values()->all();
    }

    /** @return list<string> */
    private function tables(string $name): array
    {
        $folder = Module::find($name)?->getExtraPath('database/migrations');

        if (! $folder || ! is_dir($folder)) {
            return [];
        }

        return collect(File::files($folder))
            ->flatMap(fn ($file) => preg_match_all('/Schema::create\(\s*[\'"]([\w]+)[\'"]/', $file->getContents(), $found) ? $found[1] : [])
            ->unique()->sort()->values()->all();
    }

    /** @return list<string> */
    private function documents(string $name): array
    {
        return collect($this->answer($name, 'printables'))
            ->map(function ($class) {
                try {
                    $printable = app($class);

                    return $printable instanceof Printable ? $printable->label() : null;
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()->values()->all();
    }

    /** A hook of this one module, which may not be active yet. */
    private function answer(string $name, string $hook): array
    {
        $class = 'Modules\\'.Module::find($name)?->getName().'\\Http\\Controllers\\DataController';

        try {
            return class_exists($class) && method_exists($class, $hook) ? (array) app($class)->{$hook}() : [];
        } catch (Throwable) {
            return [];
        }
    }
}
