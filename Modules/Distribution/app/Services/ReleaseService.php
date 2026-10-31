<?php

namespace Modules\Distribution\Services;

use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Support\PlatformAudit;
use Nwidart\Modules\Facades\Module;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Publishing a module version for sites to install (docs/self-hosting-build-guide.md §6):
 * zip the module folder as it is here, hash it, sign it, keep it.
 */
class ReleaseService extends BaseService
{
    /** Never inside a release zip. */
    private const SKIP = ['tests', 'node_modules', '.git', '.github', '.DS_Store', 'Thumbs.db', '.phpunit.cache'];

    public function __construct(
        protected SigningKeys $keys,
        protected ModuleManager $modules,
    ) {
    }

    public function publish(string $name, ?string $changelog = null): array
    {
        $module = Module::find($name);

        if (! $module) {
            return $this->failure(__('body.module_not_found'));
        }

        $name = $module->getName();

        if (in_array(strtolower($name), array_map('strtolower', (array) config('distribution.never_ship', [])), true)) {
            return $this->failure(__('distribution::distribution.module_not_assignable', ['module' => $name]));
        }

        if (! $this->keys->configured()) {
            return $this->failure(__('distribution::distribution.keys_missing'));
        }

        $version = (string) $module->get('version', '1.0.0');
        $latest = Release::query()->where('module', $name)->pluck('version')
            ->sort(fn (string $a, string $b) => version_compare($b, $a))->first();

        if ($latest !== null && ! version_compare($version, $latest, '>')) {
            return $this->failure(__('distribution::distribution.version_not_newer', ['module' => $name, 'version' => $version, 'latest' => $latest]));
        }

        $path = $this->directory()."/releases/{$name}/{$name}-{$version}.zip";
        $absolute = $this->disk()->path($path);
        File::ensureDirectoryExists(dirname($absolute));

        try {
            $this->zip($module->getPath(), $name, $absolute);
        } catch (Throwable $e) {
            @unlink($absolute);
            report($e);

            return $this->failure(__('distribution::distribution.zip_failed', ['error' => $e->getMessage()]));
        }

        $sha256 = hash_file('sha256', $absolute);

        $release = Release::query()->create([
            'module' => $name,
            'version' => $version,
            'sha256' => $sha256,
            'signature' => $this->keys->sign(PackageSignature::message($name, $version, $sha256)),
            'size' => filesize($absolute),
            'path' => $path,
            'requires' => $this->modules->requires($name),
            'changelog' => $changelog ?: null,
            'published_at' => now(),
        ]);

        PlatformAudit::log('distribution_release_published', "{$name} {$version} published", $release, ['sha256' => $sha256]);

        return $this->success($release, __('distribution::distribution.release_published', ['module' => $name, 'version' => $version]));
    }

    /**
     * Withdraw a release: no site is offered it any more.
     */
    public function yank(Release $release): array
    {
        if ($release->yanked_at) {
            return $this->failure(__('distribution::distribution.release_already_yanked'));
        }

        $release->update(['yanked_at' => now()]);
        PlatformAudit::log('distribution_release_yanked', "{$release->module} {$release->version} withdrawn", $release);

        return $this->success($release, __('distribution::distribution.release_yanked', ['module' => $release->module, 'version' => $release->version]));
    }

    public function absolutePath(Release $release): string
    {
        return $this->disk()->path($release->path);
    }

    /**
     * Everything under $source except SKIP, inside one top folder named after the module.
     */
    private function zip(string $source, string $name, string $target): void
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$target}");
        }

        $source = rtrim($source, '/');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);

            if (array_intersect(explode('/', $relative), self::SKIP) !== [] || $file->isLink()) {
                continue;
            }

            $file->isDir()
                ? $zip->addEmptyDir("{$name}/{$relative}")
                : $zip->addFile($file->getPathname(), "{$name}/{$relative}");
        }

        if (! $zip->close()) {
            throw new RuntimeException("Cannot write {$target}");
        }
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
