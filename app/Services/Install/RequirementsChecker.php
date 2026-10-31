<?php

namespace App\Services\Install;

/**
 * What the server must have before installing. "required" failures stop
 * the installer; the others are warnings.
 */
class RequirementsChecker
{
    public const MIN_PHP = '8.3.0';

    /** Extensions the app needs (one database driver is checked separately). */
    public const EXTENSIONS = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'dom', 'ctype', 'fileinfo', 'curl', 'zip', 'sodium', 'json', 'bcmath', 'gd'];

    /**
     * @return list<array{label: string, ok: bool, required: bool, detail: ?string}>
     */
    public function check(): array
    {
        $checks = [[
            'label' => __('install.req_php', ['version' => self::MIN_PHP]),
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'required' => true,
            'detail' => PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'label' => __('install.req_extension', ['name' => $extension]),
                'ok' => extension_loaded($extension),
                'required' => true,
                'detail' => null,
            ];
        }

        $checks[] = [
            'label' => __('install.req_db_driver'),
            'ok' => $this->databaseDrivers() !== [],
            'required' => true,
            'detail' => implode(', ', $this->databaseDrivers()) ?: null,
        ];

        $env = base_path('.env');
        $writable = [
            '.env' => is_file($env) ? is_writable($env) : is_writable(base_path()),
            'storage' => $this->writableTree(storage_path()),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
        ];

        foreach ($writable as $path => $ok) {
            $checks[] = ['label' => __('install.req_writable', ['path' => $path]), 'ok' => $ok, 'required' => true, 'detail' => null];
        }

        // Module updates replace folders here; without it updates can't install.
        $checks[] = [
            'label' => __('install.req_writable', ['path' => 'Modules']),
            'ok' => is_writable(base_path('Modules')),
            'required' => false,
            'detail' => __('install.req_modules_writable_hint'),
        ];

        $checks[] = [
            'label' => __('install.req_symlink'),
            'ok' => function_exists('symlink'),
            'required' => false,
            'detail' => __('install.req_symlink_hint'),
        ];

        return $checks;
    }

    public function passes(): bool
    {
        return collect($this->check())->every(fn (array $check) => $check['ok'] || ! $check['required']);
    }

    /**
     * @return list<string> 'mysql' and/or 'pgsql'
     */
    public function databaseDrivers(): array
    {
        return array_values(array_filter(['mysql', 'pgsql'], fn (string $driver) => extension_loaded('pdo_'.$driver)));
    }

    private function writableTree(string $path): bool
    {
        foreach (['', '/app', '/framework', '/framework/cache', '/framework/sessions', '/framework/views', '/logs'] as $sub) {
            if (is_dir($path.$sub) && ! is_writable($path.$sub)) {
                return false;
            }
        }

        return is_writable($path);
    }
}
