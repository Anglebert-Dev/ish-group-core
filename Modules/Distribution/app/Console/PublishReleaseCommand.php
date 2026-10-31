<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Modules\Distribution\Services\ReleaseService;

/**
 *   php artisan distribution:publish Savings --changelog="Fixes the budget report"
 *
 * Publishes the module as it is in this folder, at the version in its module.json.
 */
class PublishReleaseCommand extends Command
{
    protected $signature = 'distribution:publish {module} {--changelog= : What changed, shown to sites}';

    protected $description = 'Publish a signed release of a module for self-hosted sites';

    public function handle(ReleaseService $releases): int
    {
        $result = $releases->publish($this->argument('module'), $this->option('changelog'));

        $result['status'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }
}
