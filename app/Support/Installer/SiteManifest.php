<?php

namespace App\Support\Installer;

/**
 * storage/app/distribution/site.json, written into each customer's zip by
 * our Distribution module: which site this is, where updates come from,
 * which modules to install, and the install code's hash.
 *
 * The token is moved into the database during install and removed here.
 */
class SiteManifest
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(protected ?string $path = null)
    {
        $this->path ??= storage_path('app/distribution/site.json');
        $decoded = is_file($this->path) ? json_decode((string) file_get_contents($this->path), true) : null;
        $this->data = is_array($decoded) ? $decoded : [];
    }

    public function exists(): bool
    {
        return $this->data !== [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        return array_values(array_filter((array) $this->get('modules', []), 'is_string'));
    }

    public function requiresCode(): bool
    {
        return filled($this->get('install_code_hash'));
    }

    public function checkCode(string $code): bool
    {
        $hash = (string) $this->get('install_code_hash', '');

        return $hash !== '' && password_verify(self::normalizeCode($code), $hash);
    }

    /**
     * "abcde-fghjk " → "ABCDEFGHJK": dashes and spaces are only for reading.
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function forgetToken(): void
    {
        if (! array_key_exists('token', $this->data)) {
            return;
        }

        unset($this->data['token']);
        file_put_contents($this->path, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    }
}
