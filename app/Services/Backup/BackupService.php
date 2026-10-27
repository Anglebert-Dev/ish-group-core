<?php

namespace App\Services\Backup;

use App\Models\System\System;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Backup = one zip with a database dump (database.sql or database.sqlite)
 * and the uploaded files from storage/app. Kept on the private local disk.
 */
class BackupService extends BaseService
{
    /**
     * Defaults keep backups working even for a worker started before
     * config/backup.php existed (workers keep the config they booted with).
     */
    public function directory(): string
    {
        return (string) (config('backup.directory') ?: 'backups');
    }

    public function disk()
    {
        return Storage::disk('local');
    }

    /**
     * @return Collection<int, array{name: string, size: int, created_at: int}>
     */
    public function list(): Collection
    {
        $directory = $this->directory();

        return collect($this->disk()->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => $this->disk()->size($path),
                'created_at' => $this->disk()->lastModified($path),
            ])
            // Names are timestamped (backup-Y-m-d-His), so they sort by age.
            ->sortByDesc('name')
            ->values();
    }

    public function create(): array
    {
        $directory = $this->directory();
        $this->disk()->makeDirectory($directory);

        $name = 'backup-'.now()->format('Y-m-d-His').'.zip';
        $zipPath = $this->disk()->path($directory.'/'.$name);
        $work = storage_path('app/backup-tmp-'.uniqid());
        @mkdir($work, 0700, true);

        try {
            $dump = $this->dumpDatabase($work);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create '.$zipPath);
            }

            $zip->addFile($dump, basename($dump));

            foreach (config('backup.include') ?: ['private', 'public'] as $folder) {
                $root = storage_path('app/'.$folder);
                if (! is_dir($root)) {
                    continue;
                }

                foreach ((new Finder)->files()->in($root)->ignoreDotFiles(true)->notPath('#^'.preg_quote($directory, '#').'#') as $file) {
                    $zip->addFile($file->getRealPath(), 'files/'.$folder.'/'.$file->getRelativePathname());
                }
            }

            $zip->addFromString('backup.json', json_encode([
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'database' => config('database.default'),
            ], JSON_PRETTY_PRINT));
            $zip->close();
        } catch (\Throwable $e) {
            @unlink($zipPath);
            report($e);

            return $this->failure(__('body.backup_failed', ['error' => $e->getMessage()]));
        } finally {
            $this->deleteDirectory($work);
        }

        $this->prune();
        System::addProperty('backup_last_at', now()->toIso8601String());

        return $this->success($name, __('body.backup_created', ['name' => $name]));
    }

    public function path(string $name): ?string
    {
        $relative = $this->directory().'/'.basename($name);

        return $this->disk()->exists($relative) ? $this->disk()->path($relative) : null;
    }

    public function delete(string $name): bool
    {
        return $this->disk()->delete($this->directory().'/'.basename($name));
    }

    /**
     * Keep only the newest "backup_keep" archives (0 = keep all).
     */
    public function prune(): void
    {
        $keep = (int) System::getProperty('backup_keep', 7);

        if ($keep <= 0) {
            return;
        }

        $this->list()->slice($keep)->each(fn ($backup) => $this->delete($backup['name']));
    }

    private function dumpDatabase(string $work): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        switch ($config['driver']) {
            case 'sqlite':
                $target = $work.'/database.sqlite';
                if (! @copy($config['database'], $target)) {
                    throw new RuntimeException('Cannot copy the SQLite database.');
                }

                return $target;

            case 'pgsql':
                $target = $work.'/database.sql';
                $result = Process::env(['PGPASSWORD' => (string) ($config['password'] ?? '')])
                    ->timeout(900)
                    ->run([
                        config('backup.pg_dump') ?: 'pg_dump', '--no-owner', '--no-privileges',
                        '-h', (string) $config['host'], '-p', (string) $config['port'],
                        '-U', (string) $config['username'], '-f', $target, (string) $config['database'],
                    ]);
                break;

            case 'mysql':
            case 'mariadb':
                $target = $work.'/database.sql';
                $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
                    ->timeout(900)
                    ->run([
                        config('backup.mysqldump') ?: 'mysqldump', '--single-transaction', '--routines',
                        '-h', (string) $config['host'], '-P', (string) $config['port'],
                        '-u', (string) $config['username'], '--result-file='.$target, (string) $config['database'],
                    ]);
                break;

            default:
                throw new RuntimeException('Unsupported database driver: '.$config['driver']);
        }

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Database dump failed.');
        }

        return $target;
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
