<?php

namespace App\Services\Updates;

use App\Services\Backup\BackupService;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Installs one downloaded module package (docs/self-hosting-build-guide.md §5.2):
 *
 *  1. check its sha256 and our signature, before opening it
 *  2. extract it safely into staging; module.json must match the offer
 *  3. move the current folder to a backup, put the new one in place
 *  4. run core:module install|update in a fresh process (new code loads)
 *  5. on failure: roll back its migrations, put the old folder back
 *
 * Module migrations should be additive: MySQL cannot undo schema changes in
 * a transaction, so a rollback relies on each migration's down().
 */
class ModulePackageInstaller
{
    /** Old versions kept per module, newest first. */
    public const KEEP_BACKUPS = 3;

    public function __construct(
        protected ModuleManager $modules,
        protected PermissionService $permissions,
    ) {
    }

    /**
     * @param  array{module: string, action: string, version: string, sha256: string, signature: string, requires?: list<string>}  $offer
     * @return array{module: string, action: string, from_version: ?string, to_version: string, status: string, error: ?string, duration_ms: int}
     */
    public function install(array $offer, string $zipPath): array
    {
        $started = microtime(true);
        $module = $offer['module'];
        $from = $this->modules->installedVersion($module);
        $result = fn (string $status, ?string $error = null) => [
            'module' => $module,
            'action' => $offer['action'],
            'from_version' => $from,
            'to_version' => $offer['version'],
            'status' => $status,
            'error' => $error ? Str::limit($error, 2000) : null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        $staging = storage_path('app/updates/staging/'.Str::uuid());

        try {
            $this->verify($offer, $zipPath);
            $source = app(SafeZipExtractor::class)->extract($zipPath, $module, $staging);
            $this->checkManifest($source, $offer);
            $this->checkRequirements($offer);
        } catch (Throwable $e) {
            File::deleteDirectory($staging);

            return $result('failed', $e->getMessage());
        }

        $target = base_path('Modules/'.$module);
        $backup = is_dir($target) ? storage_path("app/updates/backups/{$module}-".($from ?? 'unknown').'-'.now()->format('YmdHis')) : null;
        $batch = $this->latestBatch();

        try {
            if ($backup) {
                File::ensureDirectoryExists(dirname($backup));
                $this->move($target, $backup);
            }

            $this->move($source, $target);
            $this->forgetCachedCode();
            $this->runModuleCommand($offer['action'] === 'install' && ! $from ? 'install' : 'update', $module);
        } catch (Throwable $e) {
            $this->rollBack($module, $target, $backup, $batch);

            return $result('rolled_back', $e->getMessage());
        } finally {
            File::deleteDirectory($staging);
        }

        $this->pruneBackups($module);

        return $result('success');
    }

    /**
     * Optional database backup before updating; never blocks the update.
     */
    public function backupDatabase(): ?string
    {
        try {
            $created = app(BackupService::class)->create();

            return $created['status'] ? (string) $created['data'] : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function verify(array $offer, string $zipPath): void
    {
        if (! is_file($zipPath) || ! hash_equals($offer['sha256'], (string) hash_file('sha256', $zipPath))) {
            throw new UpdateException(__('updates.hash_mismatch'));
        }

        $message = PackageSignature::message($offer['module'], $offer['version'], $offer['sha256']);

        if (! PackageSignature::verify($message, $offer['signature'], (array) config('distribution.public_keys', []))) {
            throw new UpdateException(__('updates.bad_package_signature'));
        }

        if (in_array(strtolower($offer['module']), array_map('strtolower', (array) config('distribution.never_ship', [])), true)) {
            throw new UpdateException(__('updates.not_allowed', ['module' => $offer['module']]));
        }
    }

    private function checkManifest(string $source, array $offer): void
    {
        $json = is_file($source.'/module.json') ? json_decode((string) file_get_contents($source.'/module.json'), true) : null;

        if (! is_array($json) || ($json['name'] ?? null) !== $offer['module'] || (string) ($json['version'] ?? '') !== $offer['version']) {
            throw new UpdateException(__('updates.manifest_mismatch'));
        }
    }

    private function checkRequirements(array $offer): void
    {
        $missing = array_values(array_filter((array) ($offer['requires'] ?? []), fn (string $name) => ! $this->modules->isInstalled($name)));

        if ($missing !== []) {
            throw new UpdateException(__('body.module_requires', ['name' => $offer['module'], 'modules' => implode(', ', $missing)]));
        }
    }

    /**
     * A fresh PHP process loads the new code; where processes can't be
     * started (some shared hosts), run it here after a rescan.
     */
    private function runModuleCommand(string $action, string $module): void
    {
        if (! config('distribution.update_in_process', false) && function_exists('proc_open')) {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $process = Process::path(base_path())->timeout(600)->run([$php, 'artisan', 'core:module', $action, $module, '--no-interaction']);

            if ($process->failed()) {
                throw new UpdateException(trim($process->errorOutput() ?: $process->output()) ?: "core:module {$action} {$module} failed");
            }

            $this->modules->rescan();

            return;
        }

        $this->modules->rescan();
        $outcome = $action === 'install' ? $this->modules->install($module) : $this->modules->update($module);

        if (! $outcome['status']) {
            throw new UpdateException($outcome['message']);
        }

        $this->permissions->initPermissions(true);
    }

    private function rollBack(string $module, string $target, ?string $backup, int $batch): void
    {
        try {
            // Read before rolling back, which un-records the ones that worked.
            $recorded = DB::table('migrations')->pluck('migration')->all();

            if ($this->latestBatch() > $batch && is_dir($target.'/database/migrations')) {
                Artisan::call('migrate:rollback', ['--path' => $target.'/database/migrations', '--realpath' => true, '--force' => true]);
            }

            $this->undoFailedMigration($target, $backup, $recorded);
        } catch (Throwable $e) {
            report($e);
        }

        File::deleteDirectory($target);

        if ($backup && is_dir($backup)) {
            $this->move($backup, $target);
        }

        $this->forgetCachedCode();
        $this->modules->rescan();
    }

    /**
     * The migration that threw was never recorded, so migrate:rollback skips
     * it; on MySQL/SQLite whatever it did before failing is still there.
     * Run its down() too (errors ignored: it may have done nothing yet).
     *
     * @param  list<string>  $recorded  migrations recorded before the rollback
     */
    private function undoFailedMigration(string $target, ?string $backup, array $recorded): void
    {
        $names = fn (?string $dir) => $dir && is_dir($dir)
            ? collect(File::files($dir))->filter(fn ($file) => $file->getExtension() === 'php')->map(fn ($file) => $file->getFilenameWithoutExtension())
            : collect();

        $old = $names($backup ? $backup.'/database/migrations' : null)->all();

        $failed = $names($target.'/database/migrations')
            ->reject(fn (string $name) => in_array($name, $old, true) || in_array($name, $recorded, true))
            ->sort()
            ->first();

        if (! $failed) {
            return;
        }

        try {
            $migration = require $target.'/database/migrations/'.$failed.'.php';

            if (is_object($migration) && method_exists($migration, 'down')) {
                $migration->down();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        // Different filesystems (e.g. storage on another volume).
        if (! File::copyDirectory($from, $to)) {
            throw new UpdateException(__('updates.move_failed', ['path' => $to]));
        }

        File::deleteDirectory($from);
    }

    private function forgetCachedCode(): void
    {
        File::delete(base_path('bootstrap/cache/modules.php'));

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function latestBatch(): int
    {
        try {
            return (int) DB::table('migrations')->max('batch');
        } catch (Throwable) {
            return 0;
        }
    }

    private function pruneBackups(string $module): void
    {
        if (! is_dir(storage_path('app/updates/backups'))) {
            return;
        }

        $backups = collect(File::directories(storage_path('app/updates/backups')))
            ->filter(fn (string $dir) => str_starts_with(basename($dir), $module.'-'))
            ->sortByDesc(fn (string $dir) => filemtime($dir))
            ->values();

        $backups->slice(self::KEEP_BACKUPS)->each(fn (string $dir) => File::deleteDirectory($dir));
    }
}
