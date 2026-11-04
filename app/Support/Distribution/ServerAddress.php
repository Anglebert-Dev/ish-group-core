<?php

namespace App\Support\Distribution;

/**
 * Update traffic carries a site's token, so in production it only goes over
 * https. Addresses that never leave the machine or the local network are
 * exempt (testing with a server on the same network).
 */
final class ServerAddress
{
    public static function isSafe(string $url): bool
    {
        if (str_starts_with(strtolower($url), 'https://')) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'localhost' || $host === 'host.docker.internal' || str_ends_with($host, '.local')
            || ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    }

    /** Only production is held to it: a developer's own machine is not. */
    public static function refused(string $url): bool
    {
        return app()->environment('production') && ! self::isSafe($url);
    }
}
