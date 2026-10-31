<?php

namespace App\Services\Install;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tries a database connection on a throwaway connection name, without
 * touching the app's own configuration.
 */
class DatabaseProbe
{
    private const CONNECTION = 'install_probe';

    /**
     * @param  array{driver: string, host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return array{ok: bool, has_users: bool, message: string}
     */
    public function test(array $db): array
    {
        config(['database.connections.'.self::CONNECTION => self::connectionConfig($db)]);
        DB::purge(self::CONNECTION);

        try {
            $connection = DB::connection(self::CONNECTION);
            $connection->select('select 1');
            $hasUsers = $connection->getSchemaBuilder()->hasTable('users') && $connection->table('users')->exists();
        } catch (Throwable $e) {
            return ['ok' => false, 'has_users' => false, 'message' => __('install.db_failed', ['error' => $this->shortError($e)])];
        } finally {
            DB::purge(self::CONNECTION);
        }

        return $hasUsers
            ? ['ok' => false, 'has_users' => true, 'message' => __('install.db_has_users')]
            : ['ok' => true, 'has_users' => false, 'message' => __('install.db_ok')];
    }

    /**
     * The app's own mysql/pgsql settings with the installer's values on top.
     *
     * @param  array{driver: string, host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return array<string, mixed>
     */
    public static function connectionConfig(array $db): array
    {
        return array_merge((array) config('database.connections.'.$db['driver'], []), [
            'driver' => $db['driver'],
            'url' => null,
            'host' => $db['host'],
            'port' => (int) $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => (string) ($db['password'] ?? ''),
        ]);
    }

    private function shortError(Throwable $e): string
    {
        // PDO messages repeat the DSN; the first line is enough.
        return strtok($e->getMessage(), "\n") ?: class_basename($e);
    }
}
