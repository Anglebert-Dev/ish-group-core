<?php

namespace Modules\Distribution\Support;

use App\Services\Install\RequirementsChecker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * What a customer tells us about their server before we build their zip
 * (docs/self-hosting-build-guide.md §6). One list drives the form, the CSV
 * template and upload, the checks before a build, the installer's
 * pre-filled answers and the customer's setup guide.
 *
 * Never a password: those are typed only into the installer, on their server.
 */
final class ServerDetails
{
    /**
     * key => [rules, example]. Labels and help: distribution::distribution.server_{key}[_help].
     *
     * @return array<string, array{0: array, 1: string}>
     */
    public static function fields(): array
    {
        return [
            'hosting_type' => [['nullable', 'in:shared,vps,docker'], 'shared'],
            'php_version' => [['nullable', 'regex:/^\d+\.\d+(\.\d+)?$/'], '8.3'],
            'php_path' => [['nullable', 'string', 'max:255', 'regex:/^\/[^\s;|&`$]*$/'], '/usr/local/bin/php'],
            'install_path' => [['nullable', 'string', 'max:255', 'regex:/^\/[^\s;|&`$]*$/'], '/home/acme/finance'],
            'web_root_is_public' => [['nullable', 'boolean'], 'yes'],
            'app_url' => [['nullable', 'url:http,https', 'max:255'], 'https://finance.acme.com'],
            'timezone' => [['nullable', 'timezone:all'], 'Africa/Kigali'],
            'cron_only' => [['nullable', 'boolean'], 'yes'],
            'db_driver' => [['nullable', 'in:mysql,pgsql'], 'mysql'],
            'db_host' => [['nullable', 'string', 'max:255'], '127.0.0.1'],
            'db_port' => [['nullable', 'integer', 'between:1,65535'], '3306'],
            'db_database' => [['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'], 'acme_finance'],
            'db_username' => [['nullable', 'string', 'max:128'], 'acme_finance'],
            'mail_host' => [['nullable', 'string', 'max:255'], 'mail.acme.com'],
            'mail_port' => [['nullable', 'integer', 'between:1,65535'], '587'],
            'mail_encryption' => [['nullable', 'in:tls,ssl'], 'tls'],
            'mail_username' => [['nullable', 'string', 'max:255'], 'no-reply@acme.com'],
            'mail_from_address' => [['nullable', 'email', 'max:255'], 'no-reply@acme.com'],
            'business_name' => [['nullable', 'string', 'max:255'], 'Acme Ltd'],
            'admin_name' => [['nullable', 'string', 'max:255'], 'Jane Doe'],
            'admin_email' => [['nullable', 'email', 'max:255'], 'jane@acme.com'],
        ];
    }

    /**
     * Validate and tidy; "yes/no" style answers become booleans, blanks are dropped.
     *
     * @return array{0: array<string, mixed>, 1: list<string>} details, errors
     */
    public static function clean(array $input): array
    {
        $fields = self::fields();
        $values = [];

        foreach ($fields as $key => $field) {
            $value = $input[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '' || $value === null) {
                continue;
            }

            if (in_array('boolean', $field[0], true)) {
                $value = self::bool($value);
            }

            if (in_array($key, ['hosting_type', 'db_driver', 'mail_encryption'], true)) {
                $value = strtolower((string) $value);
                $value = ['postgres' => 'pgsql', 'postgresql' => 'pgsql', 'mariadb' => 'mysql'][$value] ?? $value;
            }

            $values[$key] = $value;
        }

        $validator = Validator::make($values, array_map(fn ($field) => $field[0], $fields), [], self::labels());

        if ($validator->fails()) {
            return [[], $validator->errors()->all()];
        }

        foreach (['db_port', 'mail_port'] as $key) {
            if (isset($values[$key])) {
                $values[$key] = (int) $values[$key];
            }
        }

        return [$values, []];
    }

    /**
     * A filled-in CSV (columns: field, value; anything else ignored).
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>} details, errors, unknown field names
     */
    public static function fromCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [[], [__('distribution::distribution.csv_unreadable')], []];
        }

        $input = [];
        $unknown = [];
        $line = 0;

        while (($cells = fgetcsv($handle)) !== false && $line < 200) {
            $line++;
            $key = strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cells[0] ?? ''))));

            if ($key === '' || $key === 'field' || str_starts_with($key, '#')) {
                continue;
            }

            array_key_exists($key, self::fields()) ? $input[$key] = $cells[1] ?? null : $unknown[] = $key;
        }

        fclose($handle);

        if (array_filter($input, fn ($value) => trim((string) $value) !== '') === []) {
            return [[], [__('distribution::distribution.csv_empty')], $unknown];
        }

        [$details, $errors] = self::clean($input);

        return [$details, $errors, $unknown];
    }

    /**
     * The template we send: one row per field, with what to write and an example.
     */
    public static function csvTemplate(array $current = []): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['field', 'value', 'what to write', 'example']);

        foreach (self::fields() as $key => [, $example]) {
            $value = $current[$key] ?? '';
            fputcsv($out, [$key, is_bool($value) ? ($value ? 'yes' : 'no') : $value, __("distribution::distribution.server_{$key}_help"), $example]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * What the installer on their server starts with (site.json "defaults").
     *
     * @param  array<string, mixed>  $details
     * @return array<string, array<string, mixed>>
     */
    public static function installerDefaults(array $details, string $siteName): array
    {
        return array_filter([
            'database' => array_filter([
                'driver' => $details['db_driver'] ?? null,
                'host' => $details['db_host'] ?? null,
                'port' => $details['db_port'] ?? null,
                'database' => $details['db_database'] ?? null,
                'username' => $details['db_username'] ?? null,
            ], fn ($value) => $value !== null),
            'app' => array_filter([
                'name' => $siteName,
                'url' => $details['app_url'] ?? null,
                'timezone' => $details['timezone'] ?? null,
                'queue_via_scheduler' => $details['cron_only'] ?? null,
            ], fn ($value) => $value !== null),
            'mail' => array_filter([
                'host' => $details['mail_host'] ?? null,
                'port' => $details['mail_port'] ?? null,
                'scheme' => ($details['mail_encryption'] ?? null) === 'ssl' ? 'smtps' : null,
                'username' => $details['mail_username'] ?? null,
                'from_address' => $details['mail_from_address'] ?? null,
                'from_name' => isset($details['mail_host']) ? $siteName : null,
            ], fn ($value) => $value !== null),
            'admin' => array_filter([
                'business' => $details['business_name'] ?? null,
                'name' => $details['admin_name'] ?? null,
                'email' => $details['admin_email'] ?? null,
            ], fn ($value) => $value !== null),
            'server' => array_filter([
                'php_path' => $details['php_path'] ?? null,
                'install_path' => $details['install_path'] ?? null,
            ], fn ($value) => $value !== null),
        ]);
    }

    /**
     * Reasons this server can't run the app, from what they told us.
     *
     * @return list<string>
     */
    public static function problems(array $details): array
    {
        $problems = [];
        $version = $details['php_version'] ?? null;

        if ($version && version_compare(self::fullVersion($version), RequirementsChecker::MIN_PHP, '<')) {
            $problems[] = __('distribution::distribution.server_php_too_old', ['version' => $version, 'min' => RequirementsChecker::MIN_PHP]);
        }

        return $problems;
    }

    /**
     * The cron line for their server, with their PHP and folder when known.
     */
    public static function cronLine(array $details): string
    {
        $path = $details['install_path'] ?? '/path/to/the/app';
        $php = $details['php_path'] ?? 'php';

        return "* * * * * cd {$path} && {$php} artisan schedule:run >> /dev/null 2>&1";
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::fields())->keys()->mapWithKeys(fn ($key) => [$key => __("distribution::distribution.server_{$key}")])->all();
    }

    public static function display(string $key, mixed $value): string
    {
        return is_bool($value) ? __('distribution::distribution.'.($value ? 'yes' : 'no')) : (string) $value;
    }

    private static function bool(mixed $value): mixed
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            in_array($value, ['1', 'yes', 'y', 'true', 'oui', 'yego'], true) => true,
            in_array($value, ['0', 'no', 'n', 'false', 'non', 'oya'], true) => false,
            default => $value, // left for validation to reject
        };
    }

    private static function fullVersion(string $version): string
    {
        return substr_count($version, '.') === 1 ? $version.'.0' : $version;
    }
}
