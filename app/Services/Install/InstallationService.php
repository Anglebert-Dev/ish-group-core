<?php

namespace App\Services\Install;

use App\Models\System\System;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Business\PlatformSetupService;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use App\Support\Installer\Installer;
use App\Support\Installer\SiteManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Installs a fresh copy (docs/self-hosting-build-guide.md §5.1). One routine
 * for the web installer and core:setup.
 *
 * Each finished step is saved in storage/app/installing.json, so a request
 * that times out half-way can simply be run again and carries on.
 *
 * $data:
 *   database  driver, host, port, database, username, password
 *   app       name, url, timezone, queue_via_scheduler
 *   mail      host, port, scheme, username, password, from_address, from_name (or null: emails go to the log)
 *   admin     business, name, email, password
 *   modules   names to install (default: the zip's site.json)
 *   skip_env  true when the environment is set from outside (Docker); .env is not written
 */
class InstallationService extends BaseService
{
    public const STEPS = ['env', 'migrate', 'platform', 'modules', 'site', 'storage', 'finish'];

    public function __construct(
        protected PlatformSetupService $platform,
        protected ModuleManager $modules,
        protected PermissionService $permissions,
        protected DatabaseProbe $probe,
    ) {
    }

    /**
     * @param  callable(string): void|null  $onStep  told the name of each step as it starts
     * @return array{status: bool, data: array{business_id: ?int, modules: list<string>}|null, message: string}
     */
    public function run(array $data, ?callable $onStep = null): array
    {
        if (Installer::isInstalled()) {
            return $this->failure(__('install.already_installed'));
        }

        File::ensureDirectoryExists(storage_path('framework'));
        $lock = fopen(storage_path('framework/installer.lock'), 'c');

        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->failure(__('install.already_running'));
        }

        $step = null;

        try {
            $done = $this->progress();

            if (empty($data['skip_env'])) {
                $this->applyRuntime($data);
            }

            if ($done === [] && $this->databaseHasUsers()) {
                return $this->failure(__('install.db_has_users'));
            }

            foreach (self::STEPS as $step) {
                if (in_array($step, $done, true)) {
                    continue;
                }

                $onStep && $onStep($step);
                $this->{'step'.ucfirst($step)}($data);

                $done[] = $step;
                $this->saveProgress($done);
            }

            @unlink($this->progressPath());

            return $this->success([
                'business_id' => (int) System::getProperty('primary_business_id') ?: null,
                'modules' => $this->modulesToInstall($data),
            ], __('install.done'));
        } catch (Throwable $e) {
            report($e);

            $error = strtok($e->getMessage(), "\n") ?: class_basename($e);

            return $this->failure($step
                ? __('install.step_failed', ['step' => __('install.step_'.$step), 'error' => $error])
                : $error);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * A previous run stopped half-way; its users are its own, not a sign
     * that the database belongs to another install.
     */
    public function resuming(): bool
    {
        return $this->progress() !== [];
    }

    /**
     * What goes into .env.
     *
     * @return array<string, string|bool|null>
     */
    public function envValues(array $data): array
    {
        $db = $data['database'];
        $app = $data['app'];
        $mail = $data['mail'] ?? null;

        return [
            'APP_NAME' => $app['name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim($app['url'], '/'),
            'APP_TIMEZONE' => $app['timezone'],
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => $db['driver'],
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            'SESSION_DRIVER' => 'database',
            'SESSION_ENCRYPT' => true,
            'SESSION_SECURE_COOKIE' => str_starts_with($app['url'], 'https://'),
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'QUEUE_VIA_SCHEDULER' => (bool) ($app['queue_via_scheduler'] ?? false),
            'MAIL_MAILER' => $mail ? 'smtp' : 'log',
            'MAIL_SCHEME' => $mail['scheme'] ?? 'null',
            'MAIL_HOST' => $mail['host'] ?? '127.0.0.1',
            'MAIL_PORT' => (string) ($mail['port'] ?? 2525),
            'MAIL_USERNAME' => ($mail['username'] ?? null) ?: 'null',
            'MAIL_PASSWORD' => ($mail['password'] ?? null) ?: 'null',
            'MAIL_FROM_ADDRESS' => $mail['from_address'] ?? 'hello@example.com',
            'MAIL_FROM_NAME' => ($mail['from_name'] ?? null) ?: $app['name'],
        ];
    }

    /**
     * Requirements first, so each module finds what it needs installed.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function installOrder(array $names): array
    {
        $ordered = [];

        $visit = function (string $name, array $path) use (&$visit, &$ordered) {
            if (in_array($name, $ordered, true) || in_array($name, $path, true)) {
                return;
            }

            foreach ($this->modules->requires($name) as $required) {
                $visit($required, [...$path, $name]);
            }

            $ordered[] = $name;
        };

        foreach ($names as $name) {
            $visit($name, []);
        }

        return $ordered;
    }

    protected function stepEnv(array $data): void
    {
        if (! empty($data['skip_env'])) {
            return;
        }

        $env = new EnvWriter;
        $values = $this->envValues($data);

        // CLI installs may start without a key (the web installer already made one).
        if (! str_starts_with((string) $env->get('APP_KEY'), 'base64:')) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
            $this->useKey($values['APP_KEY']);
        }

        $env->set($values);
    }

    protected function stepMigrate(array $data): void
    {
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }

        $this->permissions->initPermissions(true);
    }

    protected function stepPlatform(array $data): void
    {
        if (User::query()->withTrashed()->exists()) {
            return; // resumed after this step had already run
        }

        $result = $this->platform->createFirstPlatform($data['admin'] + ['timezone' => $data['app']['timezone'] ?? null]);

        if (! $result['status']) {
            throw new RuntimeException($result['message']);
        }
    }

    protected function stepModules(array $data): void
    {
        foreach ($this->installOrder($this->modulesToInstall($data)) as $name) {
            if (! $this->modules->exists($name)) {
                throw new RuntimeException(__('body.module_not_found').": {$name}");
            }

            if ($this->modules->isInstalled($name)) {
                continue;
            }

            $result = $this->modules->install($name);

            if (! $result['status']) {
                throw new RuntimeException($result['message']);
            }
        }
    }

    /**
     * The update link: site id, server and token move into the database
     * (token encrypted) and the token leaves site.json.
     */
    protected function stepSite(array $data): void
    {
        $manifest = app(SiteManifest::class);

        if (! $manifest->exists()) {
            return;
        }

        foreach (['site_uuid' => 'distribution_site_uuid', 'server_url' => 'distribution_server_url'] as $key => $property) {
            if (filled($manifest->get($key))) {
                System::addProperty($property, (string) $manifest->get($key));
            }
        }

        if (filled($manifest->get('token'))) {
            System::addProperty('distribution_token', Crypt::encryptString((string) $manifest->get('token')));
            $manifest->forgetToken();
        }
    }

    /**
     * public/storage → storage/app/public. Hosts that forbid symlinks get
     * uploads stored straight in public/storage instead.
     */
    protected function stepStorage(array $data): void
    {
        if (file_exists(public_path('storage'))) {
            return;
        }

        try {
            if (function_exists('symlink') && Artisan::call('storage:link') === 0 && file_exists(public_path('storage'))) {
                return;
            }
        } catch (Throwable) {
            // symlink() disabled by the host: fall through
        }

        File::ensureDirectoryExists(public_path('storage'));
        config(['filesystems.disks.public.root' => public_path('storage')]);

        if (empty($data['skip_env'])) {
            (new EnvWriter)->set(['PUBLIC_DISK_IN_PUBLIC' => true]);
        }
    }

    protected function stepFinish(array $data): void
    {
        Installer::markInstalled(version: (string) config('core.version'));

        $businessId = (int) System::getProperty('primary_business_id') ?: null;

        audit('platform_installed', 'Platform installed', null, [
            'modules' => $this->modulesToInstall($data),
            'database' => DB::connection()->getDriverName(),
            'version' => config('core.version'),
        ], $businessId);
    }

    /**
     * @return list<string>
     */
    protected function modulesToInstall(array $data): array
    {
        return array_values(array_unique($data['modules'] ?? app(SiteManifest::class)->modules()));
    }

    /**
     * Point this process at the database, app and mail settings being installed.
     */
    protected function applyRuntime(array $data): void
    {
        $db = $data['database'];
        $app = $data['app'];

        config([
            'database.default' => $db['driver'],
            'database.connections.'.$db['driver'] => DatabaseProbe::connectionConfig($db),
            'app.name' => $app['name'],
            'app.url' => rtrim($app['url'], '/'),
            'app.timezone' => $app['timezone'],
        ]);

        if ($mail = $data['mail'] ?? null) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => array_merge((array) config('mail.mailers.smtp'), MailProbe::mailerConfig($mail)),
                'mail.from' => ['address' => $mail['from_address'], 'name' => ($mail['from_name'] ?? null) ?: $app['name']],
            ]);
        }

        DB::purge($db['driver']);

        // The cache configured so far may point at a database that doesn't
        // exist yet (a terminal install starts before .env does).
        config(['cache.default' => 'array']);
        app('cache')->forgetDriver();
        app(PermissionRegistrar::class)->initializeCache();
    }

    protected function databaseHasUsers(): bool
    {
        try {
            return DB::connection()->getSchemaBuilder()->hasTable('users') && DB::table('users')->exists();
        } catch (Throwable $e) {
            throw new RuntimeException(__('install.db_failed', ['error' => strtok($e->getMessage(), "\n")]), 0, $e);
        }
    }

    protected function useKey(string $key): void
    {
        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    /**
     * @return list<string>
     */
    protected function progress(): array
    {
        $saved = is_file($this->progressPath()) ? json_decode((string) file_get_contents($this->progressPath()), true) : null;

        return is_array($saved) ? array_values(array_intersect(self::STEPS, $saved)) : [];
    }

    /**
     * @param  list<string>  $done
     */
    protected function saveProgress(array $done): void
    {
        File::ensureDirectoryExists(dirname($this->progressPath()));
        file_put_contents($this->progressPath(), json_encode($done), LOCK_EX);
    }

    protected function progressPath(): string
    {
        return storage_path('app/installing.json');
    }
}
