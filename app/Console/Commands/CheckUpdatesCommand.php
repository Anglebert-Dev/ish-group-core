<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateService;
use Illuminate\Console\Command;

/**
 * Self-hosted servers: ask our update server what's new. Installs straight
 * away when this site is set to automatic updates (or with --install);
 * otherwise the updates wait on Super Admin → Updates. Runs hourly.
 */
class CheckUpdatesCommand extends Command
{
    protected $signature = 'core:updates {--install : Install what is available even when updates are manual}';

    protected $description = 'Check for module updates (and install them when set to automatic)';

    public function handle(UpdateService $updates): int
    {
        if (! $updates->linked()) {
            $this->components->info(__('updates.not_linked'));

            return self::SUCCESS;
        }

        $result = $updates->run((bool) $this->option('install'));

        foreach ((array) ($result['data'] ?? []) as $item) {
            if (isset($item['status'])) {
                $line = "{$item['module']} {$item['to_version']}: ".__('updates.result_'.$item['status']).($item['error'] ? " — {$item['error']}" : '');
                $item['status'] === 'success' ? $this->components->info($line) : $this->components->error($line);
            }
        }

        $result['status'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }
}
