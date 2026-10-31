<?php

namespace App\Support\Installer;

/**
 * Runs at the top of bootstrap/app.php, before Laravel reads .env, on a
 * copy that is not installed yet (docs/self-hosting-build-guide.md §5.1):
 *
 *  - creates .env from .env.example when it is missing
 *  - generates the APP_KEY on this server (we never ship one)
 *  - forces file sessions/cache and the sync queue for this request, since
 *    the database isn't set up yet (Laravel never overwrites a variable
 *    that is already set, so these win over .env without editing it)
 *
 * Web requests only: the CLI installer (core:setup) sets things up itself.
 */
final class PreInstall
{
    public static function prepare(string $basePath): void
    {
        if (PHP_SAPI === 'cli' || self::installed($basePath)) {
            return;
        }

        $env = $basePath.'/.env';

        if (! is_file($env) && is_file($basePath.'/.env.example')) {
            @copy($basePath.'/.env.example', $env);
        }

        self::ensureKey($basePath, $env);

        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        foreach ([
            'SESSION_DRIVER' => 'file',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => $https ? 'true' : 'false',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'single',
        ] as $key => $value) {
            self::force($key, $value);
        }
    }

    private static function installed(string $basePath): bool
    {
        if (Installer::isInstalled($basePath)) {
            return true;
        }

        // .env is not loaded yet, so read its APP_INSTALLED ourselves.
        $env = @file_get_contents($basePath.'/.env');

        return is_string($env) && preg_match('/^APP_INSTALLED\s*=\s*["\']?(true|1|yes|on)["\']?\s*$/mi', $env) === 1;
    }

    private static function ensureKey(string $basePath, string $env): void
    {
        $contents = is_file($env) ? (string) file_get_contents($env) : '';

        if (preg_match('/^APP_KEY\s*=\s*["\']?(base64:[A-Za-z0-9+\/=]{40,})["\']?\s*$/m', $contents)) {
            return;
        }

        if (is_file($env) && is_writable($env)) {
            $line = 'APP_KEY='.self::newKey();
            $contents = preg_match('/^APP_KEY\s*=.*$/m', $contents)
                ? preg_replace('/^APP_KEY\s*=.*$/m', $line, $contents, 1)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;

            file_put_contents($env, $contents, LOCK_EX);

            return;
        }

        // .env can't be written (the installer reports it): keep a key for
        // this server in storage so sessions still work meanwhile.
        $file = $basePath.'/storage/framework/installer.key';

        if (! is_file($file)) {
            @file_put_contents($file, self::newKey(), LOCK_EX);
            @chmod($file, 0600);
        }

        if (is_file($file)) {
            self::force('APP_KEY', trim((string) file_get_contents($file)));
        }
    }

    private static function newKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    private static function force(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
