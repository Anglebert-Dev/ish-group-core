<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Opens a module package without trusting it: every entry must sit inside
 * one folder named after the module — no "..", absolute paths, backslashes
 * or symlinks — and the whole thing must stay a sensible size.
 */
class SafeZipExtractor
{
    public const MAX_FILES = 20000;

    public const MAX_BYTES = 200 * 1024 * 1024;

    /**
     * @return string the extracted module folder ("$destination/$module")
     */
    public function extract(string $zipPath, string $module, string $destination): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UpdateException(__('updates.zip_unreadable'));
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
                throw new UpdateException(__('updates.zip_rejected', ['reason' => 'file count']));
            }

            $bytes = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) $stat['name'];
                $bytes += (int) $stat['size'];

                $this->checkName($name, $module);

                $zip->getExternalAttributesIndex($i, $system, $attributes);

                if ($system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new UpdateException(__('updates.zip_rejected', ['reason' => "symlink {$name}"]));
                }
            }

            if ($bytes > self::MAX_BYTES) {
                throw new UpdateException(__('updates.zip_rejected', ['reason' => 'too large']));
            }

            File::ensureDirectoryExists($destination);

            if (! $zip->extractTo($destination)) {
                throw new UpdateException(__('updates.zip_unreadable'));
            }
        } finally {
            $zip->close();
        }

        // Belt and braces: nothing may have landed outside the destination.
        $root = realpath($destination);

        foreach (File::allFiles($destination, true) as $file) {
            if (! str_starts_with((string) realpath($file->getPathname()), $root.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($destination);
                throw new UpdateException(__('updates.zip_rejected', ['reason' => 'path escape']));
            }
        }

        return $destination.'/'.$module;
    }

    private function checkName(string $name, string $module): void
    {
        $bad = $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name)
            || in_array('..', explode('/', $name), true)
            || ! str_starts_with($name, $module.'/');

        if ($bad) {
            throw new UpdateException(__('updates.zip_rejected', ['reason' => $name]));
        }
    }
}
