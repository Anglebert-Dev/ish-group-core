<?php

namespace App\Services\Install;

/**
 * What the server must have before installing. "required" failures stop
 * the installer; the others are warnings.
 */
class RequirementsChecker
{
    public const MIN_PHP = '8.3.0';

    /**
     * Extensions the app needs (one database driver is checked separately):
     * what the locked packages require, plus curl (updates), sodium
     * (signatures), bcmath (money) and intl (number formatting).
     */
    public const EXTENSIONS = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'intl', 'json', 'libxml',
        'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'simplexml', 'sodium', 'tokenizer', 'xml', 'xmlreader',
        'xmlwriter', 'zip', 'zlib',
    ];

    /** Recommended memory for exports, PDFs and updates. */
    public const MIN_MEMORY_MB = 256;

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

        // "php artisan serve" restarts itself when .env changes, which is the
        // installer's first step: the restart cuts the install off. --no-reload
        // prevents it (we can't see from here whether it was used).
        if (PHP_SAPI === 'cli-server') {
            $checks[] = [
                'label' => __('install.req_dev_server'),
                'ok' => false,
                'required' => false,
                'detail' => __('install.req_dev_server_hint'),
            ];
        }

        // Module updates replace folders here; without it updates can't install.
        $checks[] = [
            'label' => __('install.req_writable', ['path' => 'Modules']),
            'ok' => is_writable(base_path('Modules')),
            'required' => false,
            'detail' => __('install.req_modules_writable_hint'),
        ];

        // Only core updates need it, and a standalone copy gets none.
        if (! config('distribution.standalone')) {
            $checks[] = [
                'label' => __('install.req_proc_open'),
                'ok' => function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
                'required' => false,
                'detail' => __('install.req_proc_open_hint'),
            ];
        }

        $memory = $this->megabytes((string) ini_get('memory_limit'));
        $checks[] = [
            'label' => __('install.req_memory', ['mb' => self::MIN_MEMORY_MB]),
            'ok' => $memory === -1 || $memory >= self::MIN_MEMORY_MB,
            'required' => false,
            'detail' => __('install.req_memory_hint', ['now' => ini_get('memory_limit')]),
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

    /** "256M" → 256, "1G" → 1024, "-1" → -1 (no limit). */
    private function megabytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024,
            'M' => $number,
            'K' => intdiv($number, 1024),
            default => intdiv($number, 1048576),
        };
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
