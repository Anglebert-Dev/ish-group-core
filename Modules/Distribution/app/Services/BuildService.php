<?php

namespace Modules\Distribution\Services;

use App\Services\BaseService;
use App\Services\Install\EnvWriter;
use App\Support\Installer\SiteManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Distribution\Enums\BuildStatus;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Support\PlatformAudit;
use Modules\Distribution\Support\ServerDetails;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;
use ZipArchive;

/**
 * A ready-to-extract zip for one site (docs/self-hosting-build-guide.md §6):
 * the last commit of the core, only that site's modules, production vendor,
 * our public key, and a site.json linking it back to us.
 *
 * start() records the build and makes the install code (kept encrypted, to email it);
 * run() does the work, right after, in the same request (about 10 s).
 */
class BuildService extends BaseService
{
    /** Kept for download this long, then deleted. */
    public const KEEP_DAYS = 14;

    /** Removed from every build, whatever the site. */
    private const STRIP = [
        'tests', 'phpunit.xml', 'docs', 'PROGRESS.md', 'README.md', '.github', '.editorconfig', '.gitattributes', '.npmrc',
        'database/seeders/DemoFactorySeeder.php', 'vite.config.js', 'package.json', 'package-lock.json',
    ];

    /** Unambiguous characters for install codes (no 0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        protected SiteService $sites,
        protected SigningKeys $keys,
    ) {
    }

    /**
     * Queue a build. The install code is returned here and never again.
     *
     * @return array{status: bool, data: array{build: Build, code: string}|null, message: string}
     */
    public function start(Site $site): array
    {
        if ($site->moduleNames() === []) {
            return $this->failure(__('distribution::distribution.build_needs_modules'));
        }

        if (! $this->keys->configured()) {
            return $this->failure(__('distribution::distribution.keys_missing'));
        }

        // What the customer told us rules their server out: no point building.
        if ($problems = ServerDetails::problems((array) $site->server_details)) {
            return $this->failure(__('distribution::distribution.build_blocked', ['problems' => implode(' ', $problems)]));
        }

        // A second click while one is being built would only make a duplicate.
        if ($site->builds()->where('status', BuildStatus::Building)->where('updated_at', '>', now()->subMinutes(30))->exists()) {
            return $this->failure(__('distribution::distribution.build_in_progress'));
        }

        $code = $this->installCode();

        $build = Build::query()->create([
            'site_id' => $site->id,
            'modules' => $site->moduleNames(),
            'install_code_hash' => password_hash(SiteManifest::normalizeCode($code), PASSWORD_DEFAULT),
            'install_code' => $code, // encrypted; emailed to the customer with the download link
            'status' => BuildStatus::Queued,
        ]);

        PlatformAudit::log('distribution_build_started', "Zip build started for site {$site->name}", $build, ['modules' => $build->modules]);

        return $this->success(['build' => $build, 'code' => $code], __('distribution::distribution.build_ready'));
    }

    /**
     * Remove a build: its zip file, the row, and the update token made for
     * it (a server already installed from this zip stops getting updates).
     */
    public function delete(Build $build): array
    {
        if ($build->status === BuildStatus::Building && $build->updated_at?->gt(now()->subMinutes(30))) {
            return $this->failure(__('distribution::distribution.build_in_progress'));
        }

        if ($build->path) {
            $this->disk()->delete($build->path);
        }

        $build->site->tokens()->where('build_id', $build->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $build->delete();

        PlatformAudit::log('distribution_build_deleted', "Zip deleted for site {$build->site->name}", $build, ['file' => $build->filename, 'status' => $build->status->value]);

        return $this->success(null, __('distribution::distribution.build_deleted'));
    }

    /**
     * @param  bool  $vendor  false skips Composer and the smoke test (tests)
     */
    public function run(Build $build, bool $vendor = true): array
    {
        $build->update(['status' => BuildStatus::Building, 'error' => null]);
        $site = $build->site;
        $stage = $this->disk()->path($this->directory().'/work/'.$build->uuid);
        $root = Str::slug($site->name) ?: 'site';

        try {
            File::deleteDirectory($stage);
            File::ensureDirectoryExists($stage);

            $coreVersion = $this->export($stage);
            $this->strip($stage, (array) $build->modules);

            if ($vendor) {
                $this->vendor($stage);
            }

            $token = $this->sites->issueToken($site, $build->id);
            $tokenId = $site->tokens()->where('build_id', $build->id)->value('id');
            $this->personalise($stage, $site, $build, $token, $coreVersion);

            if ($vendor) {
                $this->smokeTest($stage);
            }

            $filename = $root.'-'.now()->format('Ymd-His').'.zip';
            $path = $this->directory().'/builds/'.$filename;
            $absolute = $this->disk()->path($path);
            File::ensureDirectoryExists(dirname($absolute));
            $this->zip($stage, $root, $absolute);

            $build->update([
                'token_id' => $tokenId,
                'core_version' => $coreVersion,
                'filename' => $filename,
                'path' => $path,
                'sha256' => hash_file('sha256', $absolute),
                'size' => filesize($absolute),
                'status' => BuildStatus::Ready,
                'expires_at' => now()->addDays(self::KEEP_DAYS),
            ]);

            PlatformAudit::log('distribution_build_ready', "Zip built for site {$site->name}", $build, ['file' => $filename, 'core_version' => $coreVersion]);

            return $this->success($build, __('distribution::distribution.build_ready'));
        } catch (Throwable $e) {
            report($e);
            $build->update(['status' => BuildStatus::Failed, 'error' => Str::limit($e->getMessage(), 2000)]);

            // A token from a build nobody can download must not stay valid.
            $build->site->tokens()->where('build_id', $build->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $this->failure(__('distribution::distribution.build_failed', ['error' => Str::limit($e->getMessage(), 300)]));
        } finally {
            File::deleteDirectory($stage);
        }
    }

    public function absolutePath(Build $build): string
    {
        return $this->disk()->path((string) $build->path);
    }

    /**
     * Committed files only (git archive): nothing local, ignored or secret.
     */
    private function export(string $stage): string
    {
        $archive = $stage.'.export.zip';

        try {
            $this->mustRun(['git', '-C', base_path(), 'archive', '--format=zip', '-o', $archive, 'HEAD']);

            $zip = new ZipArchive;

            if ($zip->open($archive) !== true || ! $zip->extractTo($stage)) {
                throw new RuntimeException('Cannot extract the git export.');
            }

            $zip->close();
        } finally {
            @unlink($archive);
        }

        return trim($this->mustRun(['git', '-C', base_path(), 'describe', '--tags', '--always']));
    }

    /**
     * Only the site's modules; never Billing or Distribution; no tests or dev files.
     *
     * @param  list<string>  $modules
     */
    private function strip(string $stage, array $modules): void
    {
        $keep = array_map('strtolower', $modules);
        $neverShip = array_map('strtolower', (array) config('distribution.never_ship', []));

        foreach (File::directories($stage.'/Modules') as $dir) {
            $name = strtolower(basename($dir));

            if (! in_array($name, $keep, true) || in_array($name, $neverShip, true)) {
                File::deleteDirectory($dir);
            }
        }

        foreach (File::directories($stage.'/Modules') as $dir) {
            File::deleteDirectory($dir.'/tests');
        }

        foreach (self::STRIP as $path) {
            File::isDirectory("{$stage}/{$path}") ? File::deleteDirectory("{$stage}/{$path}") : File::delete("{$stage}/{$path}");
        }

        foreach (File::glob($stage.'/bootstrap/cache/*.php') as $file) {
            File::delete($file);
        }

        // Belt and braces: a build containing these must never leave this server.
        foreach ($neverShip as $name) {
            foreach (File::directories($stage.'/Modules') as $dir) {
                if (strtolower(basename($dir)) === $name) {
                    throw new RuntimeException("Refusing to ship {$name}.");
                }
            }
        }
    }

    /**
     * Start from our vendor (fast, offline), then let Composer drop the dev
     * packages and build an autoloader for only the modules in this zip.
     */
    private function vendor(string $stage): void
    {
        if (is_dir(base_path('vendor'))) {
            File::copyDirectory(base_path('vendor'), $stage.'/vendor');
        }

        // Run as "php composer …": a web request's environment may lack php
        // on PATH and HOME, which Composer needs. It downloads nothing here
        // (the packages come from our vendor), so its own home in storage is enough.
        $env = [
            'PATH' => dirname($this->php()).PATH_SEPARATOR.(getenv('PATH') ?: '/usr/bin:/bin'),
            'COMPOSER_HOME' => getenv('COMPOSER_HOME') ?: $this->disk()->path($this->directory().'/composer-home'),
        ];
        File::ensureDirectoryExists($env['COMPOSER_HOME']);

        $this->mustRun([
            $this->php(), $this->composer(), 'install',
            '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader', '--no-scripts', '--no-progress',
        ], $stage, 900, $env);
    }

    /**
     * What makes this zip this site's: site.json, our public key, the core
     * version and a production .env.example.
     */
    private function personalise(string $stage, Site $site, Build $build, string $token, string $coreVersion): void
    {
        File::ensureDirectoryExists($stage.'/storage/app/distribution');
        File::put($stage.'/storage/app/distribution/site.json', json_encode([
            'site_uuid' => $site->uuid,
            'site_name' => $site->name,
            'server_url' => rtrim((string) config('app.url'), '/'),
            'token' => $token,
            'modules' => array_values((array) $build->modules),
            'install_code_hash' => $build->install_code_hash,
            'build_id' => $build->uuid,
            // The customer's answers: their installer starts filled in (never passwords).
            'defaults' => ServerDetails::installerDefaults((array) $site->server_details, $site->name),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $publicKeys = array_values(array_unique([$this->keys->publicKey(), ...(array) config('distribution.public_keys', [])]));

        // Only the key list changes; the rest of the shipped config stays as written.
        $config = File::get($stage.'/config/distribution.php');
        $written = preg_replace("/^(\s*)'public_keys' => .*$/m", "\\1'public_keys' => ".var_export($publicKeys, true).',', $config, 1, $count);

        if ($count !== 1) {
            throw new RuntimeException('config/distribution.php has no public_keys line to fill in.');
        }

        File::put($stage.'/config/distribution.php', $written);

        File::put($stage.'/config/core.php', "<?php\n\nreturn ".var_export(['version' => $coreVersion], true).";\n");

        // Placeholders only: the installer writes the real values.
        (new EnvWriter($stage.'/.env.example'))->set([
            'APP_NAME' => $site->name,
            'APP_ENV' => 'production',
            'APP_KEY' => '',
            'APP_DEBUG' => false,
            'APP_URL' => $site->app_url ?: ($site->server_details['app_url'] ?? 'http://localhost'),
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => false,
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
        ]);
    }

    private function smokeTest(string $stage): void
    {
        $this->mustRun([$this->php(), 'artisan', '--version'], $stage, 120);
    }

    /** Folders PHP and Composer are usually installed in, beyond PATH. */
    private const BIN_DIRS = ['/usr/local/bin', '/opt/homebrew/bin', '/usr/bin', '/bin'];

    private function php(): string
    {
        $php = config('distribution.php')
            ?: (new PhpExecutableFinder)->find(false)
            ?: (new ExecutableFinder)->find('php', null, self::BIN_DIRS);

        if (! $php || ! is_file($php)) {
            throw new RuntimeException(__('distribution::distribution.php_missing'));
        }

        return $php;
    }

    private function composer(): string
    {
        $configured = (string) config('distribution.composer', 'composer');
        $composer = is_file($configured) ? $configured : (new ExecutableFinder)->find($configured, null, self::BIN_DIRS);

        if (! $composer) {
            throw new RuntimeException(__('distribution::distribution.composer_missing'));
        }

        return $composer;
    }

    /**
     * One top folder; Unix permissions so it extracts usable on any host.
     */
    private function zip(string $stage, string $root, string $target): void
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$target}");
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            if ($file->isLink()) {
                continue;
            }

            $name = $root.'/'.substr($file->getPathname(), strlen($stage) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($name);
                $zip->setExternalAttributesName($name.'/', ZipArchive::OPSYS_UNIX, (0040755 << 16));

                continue;
            }

            $zip->addFile($file->getPathname(), $name);
            $mode = $name === $root.'/artisan' || str_starts_with($name, $root.'/vendor/bin/') ? 0100755 : 0100644;
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, ($mode << 16));
        }

        if (! $zip->close()) {
            throw new RuntimeException("Cannot write {$target}");
        }
    }

    private function installCode(): string
    {
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    /**
     * @param  list<string>  $command
     */
    private function mustRun(array $command, ?string $cwd = null, int $timeout = 300, array $env = []): string
    {
        $result = Process::path($cwd ?? base_path())->timeout($timeout)->env($env)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(basename($command[0]).' '.($command[1] ?? '').' failed: '.Str::limit(trim($result->errorOutput() ?: $result->output()), 1500));
        }

        return $result->output();
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('distribution.disk', 'local'));
    }

    private function directory(): string
    {
        return trim((string) config('distribution.directory', 'distribution'), '/');
    }
}
