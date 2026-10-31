<?php

namespace App\Support\Distribution;

/**
 * A "link key": what a self-hosted server needs to (re)connect to our update
 * server without a new zip — our address, its site id and a fresh token,
 * in one string to paste. Shared by our Distribution module (makes it) and
 * every customer server (reads it). docs/self-hosting-build-guide.md §5.2.
 *
 * Not signed: it can't point a site at a fake server, because update answers
 * must still be signed with a key in config('distribution.public_keys').
 */
final class LinkKey
{
    public const PREFIX = 'ISH1-';

    public static function encode(string $serverUrl, string $siteUuid, string $token): string
    {
        $json = json_encode(['u' => rtrim($serverUrl, '/'), 's' => $siteUuid, 't' => $token], JSON_UNESCAPED_SLASHES);

        return self::PREFIX.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array{server_url: string, site_uuid: string, token: string}|null
     */
    public static function decode(string $key): ?array
    {
        $key = trim($key);

        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr(substr($key, strlen(self::PREFIX)), '-_', '+/'), true), true);

        $valid = is_array($data)
            && filter_var($data['u'] ?? null, FILTER_VALIDATE_URL)
            && preg_match('/^https?:\/\//', (string) $data['u'])
            && preg_match('/^[0-9a-f-]{36}$/i', (string) ($data['s'] ?? ''))
            && preg_match('/^ish_[0-9a-f]{48}$/', (string) ($data['t'] ?? ''));

        return $valid ? ['server_url' => rtrim($data['u'], '/'), 'site_uuid' => $data['s'], 'token' => $data['t']] : null;
    }
}
