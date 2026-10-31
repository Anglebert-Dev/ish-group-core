<?php

namespace App\Services\Install;

use InvalidArgumentException;

/**
 * Sets keys in .env, keeping every other line (APP_KEY included).
 * Values are quoted so passwords come back exactly as typed; newlines are
 * refused so a value can never add a line of its own.
 */
class EnvWriter
{
    public function __construct(protected ?string $path = null)
    {
        $this->path ??= base_path('.env');
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->current();

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Invalid .env key: {$key}");
            }

            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        $temp = $this->path.'.tmp-'.bin2hex(random_bytes(4));

        if (! is_writable(dirname($this->path)) || @file_put_contents($temp, $contents, LOCK_EX) === false) {
            throw new InvalidArgumentException(__('install.env_not_writable'));
        }

        @chmod($temp, 0640);

        if (! @rename($temp, $this->path)) {
            @unlink($temp);
            throw new InvalidArgumentException(__('install.env_not_writable'));
        }
    }

    public function get(string $key): ?string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'\s*=(.*)$/m', $this->current(), $match)) {
            return null;
        }

        return \Dotenv\Dotenv::parse($key.'='.$match[1])[$key] ?? null;
    }

    public function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException(__('install.env_value_newline'));
        }

        if (preg_match('/^[A-Za-z0-9_.:\/@+=,-]*$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function current(): string
    {
        if (is_file($this->path)) {
            return (string) file_get_contents($this->path);
        }

        $example = dirname($this->path).'/.env.example';

        return is_file($example) ? (string) file_get_contents($example) : '';
    }
}
