<?php

namespace App\Services\Updates;

use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Installs a core update (docs/self-hosting-build-guide.md §5.3).
 *
 * Everything that can be checked is checked here, in the running code,
 * before anything changes: PHP version, permissions, sha256, our signature,
 * a safe extraction, the version inside. The swap itself runs in
 * apply-core.php (its own process: this code is what gets replaced), and
 * the result is recorded by "core:finish-core-update" in yet another
 * process, so it runs in whichever code is in place afterwards.
 */
class CoreUpdater
{
    /** Code backups kept from earlier core updates. */
    public const KEEP_BACKUPS = 2;

    /** Folders and files a core update replaces (must match apply-core.php). */
    public const PATHS = [
        'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config', 'database', 'public',
        'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock', '.htaccess',
    ];

    protected string $base;

    public function __construct(protected UpdateClient $client, ?string $basePath = null)
    {
        $this->base = rtrim($basePath ?? base_path(), '/');
    }

    /**
     * @param  array{module: string, version: string, sha256: string, signature: string, min_php?: ?string, from_version?: ?string}  $offer
     * @return array{module: string, action: string, from_version: ?string, to_version: string, status: string, error: ?string, duration_ms: int}
     */
    public function install(array $offer, string $zipPath, bool $download = true): array
    {
        $started = microtime(true);
        $result = fn (string $status, ?string $error = null) => [
            'module' => 'Core',
            'action' => 'core',
            'from_version' => (string) config('core.version'),
            'to_version' => $offer['version'],
            'status' => $status,
            'error' => $error ? Str::limit($error, 2000) : null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        $work = $this->base.'/storage/app/updates/core-'.Str::uuid();

        try {
            $this->checkServer($offer);

            if ($download) {
                File::ensureDirectoryExists(dirname($zipPath));
                $this->client->download($offer, $zipPath);
            }

            $this->verify($offer, $zipPath);
            $source = app(SafeZipExtractor::class)->extract($zipPath, 'core', $work.'/new');
            $this->checkPackage($source, $offer);
        } catch (Throwable $e) {
            File::deleteDirectory($work);

            return $result('failed', $e->getMessage());
        }

        if (config('distribution.backup_before_update', true)) {
            app(ModulePackageInstaller::class)->backupDatabase();
        }

        // The script runs from storage: the copy in app/ is about to be replaced.
        $script = $work.'/apply-core.php';
        File::copy($this->base.'/app/Support/Updates/apply-core.php', $script);
        $backup = $this->base.'/storage/app/updates/core-backups/'.(config('core.version') ?: 'unknown').'-'.now()->format('YmdHis');
        $resultFile = $work.'/result.json';

        $process = Process::path($this->base)->timeout(1800)->run([$this->php(), $script, $this->base, $source, $backup, $resultFile, $this->php()]);

        if (function_exists('opcache_reset')) {
            @opcache_reset(); // a web worker must not keep serving the old files
        }

        $applied = is_file($resultFile) ? json_decode((string) file_get_contents($resultFile), true) : null;
        $outcome = is_array($applied)
            ? $result((string) $applied['status'], $applied['error'] ?? null)
            : $result('failed', trim($process->errorOutput() ?: $process->output()) ?: 'The update script did not finish.');

        File::deleteDirectory($work);
        $this->pruneBackups();

        return $outcome;
    }

    /**
     * Record it from a fresh process, in whichever code is in place now.
     */
    public function finish(array $outcome): void
    {
        $file = $this->base.'/storage/app/updates/core-result-'.Str::uuid().'.json';
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode($outcome));

        $process = Process::path($this->base)->timeout(300)->run([$this->php(), 'artisan', 'core:finish-core-update', $file, '--no-interaction']);

        if ($process->failed()) {
            report(new UpdateException('core:finish-core-update failed: '.trim($process->errorOutput() ?: $process->output())));
        }

        File::delete($file);
    }

    private function checkServer(array $offer): void
    {
        if (! function_exists('proc_open')) {
            throw new UpdateException(__('updates.core_needs_processes'));
        }

        if (($offer['min_php'] ?? null) && version_compare(PHP_VERSION, (string) $offer['min_php'], '<')) {
            throw new UpdateException(__('updates.core_php_too_old', ['version' => PHP_VERSION, 'min' => $offer['min_php']]));
        }

        $paths = [$this->base, $this->base.'/bootstrap', $this->base.'/storage/app'];

        foreach (self::PATHS as $path) {
            if (file_exists($this->base.'/'.$path)) {
                $paths[] = is_dir($this->base.'/'.$path) ? $this->base.'/'.$path : dirname($this->base.'/'.$path);
            }
        }

        foreach (array_unique($paths) as $path) {
            if (! is_writable($path)) {
                throw new UpdateException(__('updates.core_not_writable', ['path' => Str::after($path, $this->base.'/') ?: '.']));
            }
        }
    }

    private function verify(array $offer, string $zipPath): void
    {
        if (! is_file($zipPath) || ! hash_equals($offer['sha256'], (string) hash_file('sha256', $zipPath))) {
            throw new UpdateException(__('updates.hash_mismatch'));
        }

        if (! PackageSignature::verify(PackageSignature::message('Core', $offer['version'], $offer['sha256']), $offer['signature'], (array) config('distribution.public_keys', []))) {
            throw new UpdateException(__('updates.bad_package_signature'));
        }
    }

    private function checkPackage(string $source, array $offer): void
    {
        $core = is_file($source.'/config/core.php') ? (array) require $source.'/config/core.php' : [];

        if ((string) ($core['version'] ?? '') !== $offer['version'] || ! is_file($source.'/artisan') || ! is_dir($source.'/vendor')) {
            throw new UpdateException(__('updates.manifest_mismatch'));
        }

        if (is_dir($source.'/Modules') || is_dir($source.'/storage')) {
            throw new UpdateException(__('updates.zip_rejected', ['reason' => 'core package with Modules or storage']));
        }
    }

    private function pruneBackups(): void
    {
        $dir = $this->base.'/storage/app/updates/core-backups';

        if (! is_dir($dir)) {
            return;
        }

        collect(File::directories($dir))->sortByDesc(fn (string $path) => filemtime($path))->slice(self::KEEP_BACKUPS)
            ->each(fn (string $path) => File::deleteDirectory($path));
    }

    private function php(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}
