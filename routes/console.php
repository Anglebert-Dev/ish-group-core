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
