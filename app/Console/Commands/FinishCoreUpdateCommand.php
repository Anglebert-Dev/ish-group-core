<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateService;
use Illuminate\Console\Command;

/**
 * Run by CoreUpdater in a fresh process after a core update, so the result
 * is recorded by whichever code is in place now: history, report to our
 * server, audit, notification to the admins.
 */
class FinishCoreUpdateCommand extends Command
{
    protected $signature = 'core:finish-core-update {result : JSON file with the outcome}';

    protected $description = 'Record the outcome of a core update (run by the updater, not by hand)';

    protected $hidden = true;

    public function handle(UpdateService $updates): int
    {
        $outcome = json_decode((string) @file_get_contents((string) $this->argument('result')), true);

        if (! is_array($outcome) || ! isset($outcome['status'], $outcome['to_version'])) {
            $this->error('No core update result to record.');

            return self::FAILURE;
        }

        $updates->recordCore($outcome);

        return self::SUCCESS;
    }
}
