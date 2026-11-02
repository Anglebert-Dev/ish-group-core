<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Modules\Distribution\Services\LicenceService;

/**
 * Daily: support-ending reminders (30 and 7 days before, and on the day).
 * The site page's "Send reminder now" button is the web twin.
 */
class SupportRemindersCommand extends Command
{
    protected $signature = 'distribution:support-reminders';

    protected $description = 'Email self-hosting customers whose support & updates are ending or have ended';

    public function handle(LicenceService $licences): int
    {
        $this->components->info($licences->sendDueReminders().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
