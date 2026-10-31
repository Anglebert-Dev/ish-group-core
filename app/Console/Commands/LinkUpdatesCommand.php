<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateService;
use Illuminate\Console\Command;

/**
 * Terminal twin of Super Admin → Updates → Link to your provider.
 *
 *   php artisan core:link ISH1-…
 */
class LinkUpdatesCommand extends Command
{
    protected $signature = 'core:link {key : The link key from your provider (starts with ISH1-)}';

    protected $description = '(Re)connect this server to its update server with a link key';

    public function handle(UpdateService $updates): int
    {
        $result = $updates->link((string) $this->argument('key'));

        $result['status'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }
}
