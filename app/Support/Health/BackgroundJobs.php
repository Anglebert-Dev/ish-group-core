<?php

namespace App\Support\Health;

use App\Models\System\System;
use App\Support\Installer\Installer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is anything sending queued emails and running scheduled tasks? Both need a
 * process running beside the web server (README → Run); without it, emails
 * just wait. Superadmins see a banner naming the command to start.
 */
final class BackgroundJobs
{
    public const HEARTBEAT = 'scheduler_heartbeat';

    /** How long work may wait before we say something. */
    public const GRACE_MINUTES = 5;

    /**
     * @return list<array{title: string, command: string}> nothing = all well
     */
    public static function problems(): array
    {
        return Cache::remember('health.background', 60, function () {
            $problems = [];
            $viaScheduler = (bool) config('queue.via_scheduler');

            if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
                $waiting = DB::table('jobs')->where('available_at', '<', now()->subMinutes(self::GRACE_MINUTES)->getTimestamp())->count();
                if ($waiting > 0) {
                    $problems[] = [
                        'title' => trans_choice('body.health_queue_stuck', $waiting, ['count' => $waiting, 'minutes' => self::GRACE_MINUTES]),
                        'command' => $viaScheduler ? self::schedulerCommand() : 'php artisan queue:work --tries=3',
                    ];
                }
            }

            $beat = System::getProperty(self::HEARTBEAT);
            $marker = is_file(Installer::markerPath()) ? json_decode((string) file_get_contents(Installer::markerPath()), true) : null;
            $installedAt = is_array($marker) ? ($marker['installed_at'] ?? null) : null;
            $settled = ! $installedAt || Carbon::parse($installedAt)->lt(now()->subMinutes(self::GRACE_MINUTES));
            if ($settled && (! $beat || Carbon::parse($beat)->lt(now()->subMinutes(self::GRACE_MINUTES)))) {
                $problems[] = [
                    'title' => __('body.health_scheduler_stopped'),
                    'command' => self::schedulerCommand(),
                ];
            }

            return $problems;
        });
    }

    /** What keeps the scheduler going here: schedule:work on a PC, cron on a server. */
    private static function schedulerCommand(): string
    {
        return PHP_SAPI === 'cli-server' || app()->environment('local')
            ? 'php artisan schedule:work'
            : '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1';
    }
}
