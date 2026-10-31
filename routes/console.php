<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Class-based commands live in app/Console/Commands and are discovered
| automatically. Modules register their own commands and schedules in
| their service providers.
*/

// Daily backup, only when switched on in Super Admin → Backups.
Schedule::command('core:backup --scheduled')->dailyAt('02:00')->withoutOverlapping();

// Shared hosting (cron only, no Supervisor): QUEUE_VIA_SCHEDULER=true lets
// schedule:run send queued emails every minute.
if (config('queue.via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
}
