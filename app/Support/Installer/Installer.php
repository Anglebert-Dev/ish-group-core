<?php

namespace App\Support\Installer;

/**
 * Is this copy of the app set up yet? (docs/self-hosting-build-guide.md §4)
 *
 * Installed = the storage/installed marker exists, or APP_INSTALLED=true
 * (our own server and the tests). Plain PHP on purpose: it is asked before
 * Laravel boots, when there may be no .env, database or APP_KEY yet.
 */
class Installer
{
    public const MARKER = 'storage/installed';

    /** Tests point the marker elsewhere so they never touch the real one. */
    private static ?string $markerOverride = null;

    public static function useMarkerPath(?string $path): void
    {
        self::$markerOverride = $path;
    }

    public static function isInstalled(?string $basePath = null): bool
    {
        $flag = $_ENV['APP_INSTALLED'] ?? $_SERVER['APP_INSTALLED'] ?? getenv('APP_INSTALLED');

        if (in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return is_file(self::markerPath($basePath));
    }

    /**
     * Write the marker; the installer refuses to run again afterwards.
     */
    public static function markInstalled(?string $basePath = null, ?string $version = null): void
    {
        $path = self::markerPath($basePath);

        if (is_file($path)) {
            return;
        }

        file_put_contents($path, json_encode([
            'installed_at' => date(DATE_ATOM),
            'version' => $version,
        ], JSON_PRETTY_PRINT).PHP_EOL);

        @chmod($path, 0444);
    }

    public static function markerPath(?string $basePath = null): string
    {
        if (self::$markerOverride !== null) {
            return self::$markerOverride;
        }

        $basePath ??= function_exists('base_path') ? base_path() : dirname(__DIR__, 3);

        return rtrim($basePath, '/').'/'.self::MARKER;
    }
}
