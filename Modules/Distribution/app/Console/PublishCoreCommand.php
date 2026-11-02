<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Modules\Distribution\Services\ReleaseService;

/**
 * Terminal twin of Self-hosting → Releases → Publish core update.
 *
 *   php artisan distribution:publish-core --changelog="Faster reports" [--test]
 */
class PublishCoreCommand extends Command
{
    protected $signature = 'distribution:publish-core {--changelog= : What changed, shown to sites} {--test : Offer it to test sites first}';

    protected $description = 'Publish a signed core update (the version in config/core.php, from the last commit)';

    public function handle(ReleaseService $releases): int
    {
        $result = $releases->publishCore($this->option('changelog'), (bool) $this->option('test'));

        $result['status'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }
}
