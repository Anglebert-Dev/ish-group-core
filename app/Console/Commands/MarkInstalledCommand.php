<?php

namespace App\Console\Commands;

use App\Support\Installer\Installer;
use Illuminate\Console\Command;

/**
 * For servers set up before the installer existed (ours): writes the
 * storage/installed marker so they never get sent to /install.
 */
class MarkInstalledCommand extends Command
{
    protected $signature = 'core:mark-installed';

    protected $description = 'Mark this server as installed (writes storage/installed)';

    public function handle(): int
    {
        if (is_file(Installer::markerPath())) {
            $this->components->info('Already marked as installed.');

            return self::SUCCESS;
        }

        Installer::markInstalled();
        audit('platform_marked_installed', 'Server marked as installed');
        $this->components->info('Marked as installed: '.Installer::markerPath());

        return self::SUCCESS;
    }
}
