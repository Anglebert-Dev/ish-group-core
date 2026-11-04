<?php

namespace Modules\InjongePay\Services;

use App\Models\System\System;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\InjongePay\Support\PlatformAlert;
use Throwable;

/**
 * Injonge Pay's scheduled jobs: on/off and timing are set on the Background
 * jobs page, and every run is recorded there so anyone can see if they work.
 */
class BackgroundJobs
{
    public const JOBS = [
        'process_inbox' => ['command' => 'injongepay:process-inbox', 'every' => 1, 'options' => [1, 2, 5]],
        'check_cashouts' => ['command' => 'injongepay:check-cashouts', 'every' => 1, 'options' => [1, 2, 5]],
        'mirror' => ['command' => 'injongepay:mirror', 'every' => 10, 'options' => [5, 10, 15, 30, 60]],
        'sweep_fees' => ['command' => 'injongepay:sweep-fees', 'every' => 10, 'options' => [5, 10, 15, 30, 60]],
        'expire_approvals' => ['command' => 'injongepay:expire-approvals', 'every' => 10, 'options' => [5, 10, 15, 30, 60]],
        'sync_merchants' => ['command' => 'injongepay:sync-merchants', 'every' => 15, 'options' => [15, 30, 60]],
        'close_day' => ['command' => 'injongepay:close-day', 'daily' => '01:30'],
        'sync_all' => ['command' => 'injongepay:sync-merchants --all', 'daily' => '02:00'],
        'prune_logs' => ['command' => 'injongepay:prune-logs', 'daily' => '03:30'],
    ];

    public const RUNNING = 'running';

    public const OK = 'ok';

    public const FAILED = 'failed';

    /** A job killed mid-run (a server restart) frees its lock after this, so it can't stay stopped for a day. */
    private const LOCK_MINUTES = 60;

    /** How long GTBank call logs are kept unless Inhills sets otherwise. */
    public const DEFAULT_LOG_DAYS = 90;

    public const MIN_LOG_DAYS = 30;

    /** A run still open after this long has stopped, whatever its row says. */
    private const STUCK_AFTER_MINUTES = 30;

    public function register(Schedule $schedule): void
    {
        $rows = $this->rows();

        foreach (self::JOBS as $key => $job) {
            $row = $rows[$key] ?? null;

            $schedule->command($job['command'])->cron($this->cron($key, $row?->timing))->withoutOverlapping(self::LOCK_MINUTES)
                ->createMutexNameUsing('injongepay-job-'.$key)
                ->when(fn () => (bool) ($row->is_enabled ?? true))
                ->before(fn () => $this->started($key))
                ->onSuccess(fn () => $this->finished($key, true))
                ->onFailure(fn () => $this->finished($key, false));
        }
    }

    /** @return list<array{key: string, enabled: bool, timing: string, options: ?array, status: string, last_finished_at: ?Carbon}> */
    public function all(): array
    {
        $rows = $this->rows();

        return collect(self::JOBS)->map(fn (array $job, string $key) => [
            'key' => $key,
            'enabled' => (bool) ($rows[$key]->is_enabled ?? true),
            'timing' => $this->timing($key, $rows[$key]->timing ?? null),
            'options' => $job['options'] ?? null,
            'status' => $this->status($key, $rows[$key] ?? null),
            'last_finished_at' => isset($rows[$key]->last_finished_at) ? Carbon::parse($rows[$key]->last_finished_at) : null,
        ])->values()->all();
    }

    /** Jobs someone should look at: off, failed or late. */
    public function needingAttention(): int
    {
        return collect($this->all())->whereIn('status', ['off', self::FAILED, 'late'])->count();
    }

    /** @param array<string, array{enabled?: mixed, timing: string}> $input */
    public function update(array $input): void
    {
        $changes = [];

        foreach ($this->all() as $job) {
            $enabled = (bool) ($input[$job['key']]['enabled'] ?? false);
            $timing = (string) $input[$job['key']]['timing'];

            if ($enabled === $job['enabled'] && $timing === $job['timing']) {
                continue;
            }

            DB::table('ijp_background_jobs')->updateOrInsert(['key' => $job['key']], ['is_enabled' => $enabled, 'timing' => $timing, 'updated_at' => now(), 'created_at' => now()]);
            $changes[$job['key']] = ['from' => ['enabled' => $job['enabled'], 'timing' => $job['timing']], 'to' => ['enabled' => $enabled, 'timing' => $timing]];
        }

        if ($changes) {
            audit('injongepay_background_jobs_changed', __('injongepay::injongepay.audit_background_jobs', ['count' => count($changes)]), null, ['changes' => $changes]);
        }
    }

    public static function logDays(): int
    {
        return (int) (System::getProperty('injongepay_bank_call_days') ?: self::DEFAULT_LOG_DAYS);
    }

    public function setLogDays(int $days): void
    {
        if ($days === self::logDays()) {
            return;
        }

        $old = self::logDays();
        System::addProperty('injongepay_bank_call_days', (string) $days);
        audit('injongepay_log_retention_changed', __('injongepay::injongepay.audit_log_retention', ['days' => $days]), null, ['old' => $old, 'new' => $days]);
    }

    public function started(string $key): void
    {
        DB::table('ijp_background_jobs')->updateOrInsert(['key' => $key], ['last_status' => self::RUNNING, 'last_started_at' => now(), 'updated_at' => now()]);
    }

    public function finished(string $key, bool $ok): void
    {
        // Told once when it starts failing, not every minute it keeps failing.
        $wasFailing = (bool) DB::table('ijp_background_jobs')->where('key', $key)->value('failing');

        DB::table('ijp_background_jobs')->updateOrInsert(['key' => $key], ['last_status' => $ok ? self::OK : self::FAILED, 'failing' => ! $ok, 'last_finished_at' => now(), 'updated_at' => now()]);

        if (! $ok && ! $wasFailing) {
            $this->tellFailed($key);
        }
    }

    public function cron(string $key, ?string $timing = null): string
    {
        $timing = $this->timing($key, $timing);

        if (isset(self::JOBS[$key]['daily'])) {
            [$hour, $minute] = array_map('intval', explode(':', $timing));

            return "{$minute} {$hour} * * *";
        }

        return (int) $timing === 60 ? '0 * * * *' : '*/'.(int) $timing.' * * * *';
    }

    private function timing(string $key, ?string $stored): string
    {
        return $stored ?: (string) (self::JOBS[$key]['every'] ?? self::JOBS[$key]['daily']);
    }

    private function status(string $key, ?object $row): string
    {
        if ($row && ! $row->is_enabled) {
            return 'off';
        }

        $started = isset($row->last_started_at) ? Carbon::parse($row->last_started_at) : null;
        $finished = isset($row->last_finished_at) ? Carbon::parse($row->last_finished_at) : null;

        if ($row->failing ?? false) {
            return self::FAILED;
        }

        if (($row->last_status ?? null) === self::RUNNING && $started?->gte(now()->subMinutes(self::STUCK_AFTER_MINUTES))) {
            return self::RUNNING;
        }

        if (! $finished) {
            return ($row->last_status ?? null) === self::RUNNING ? 'late' : 'never';
        }

        $allowed = isset(self::JOBS[$key]['daily']) ? 25 * 60 : max(10, 3 * (int) $this->timing($key, $row->timing ?? null));

        return $finished->lt(now()->subMinutes($allowed)) ? 'late' : self::OK;
    }

    /** @return array<string, object> */
    private function rows(): array
    {
        try {
            return Schema::hasTable('ijp_background_jobs') ? DB::table('ijp_background_jobs')->get()->keyBy('key')->all() : [];
        } catch (Throwable) {
            return []; // No database yet (installing): the defaults stand.
        }
    }

    private function tellFailed(string $key): void
    {
        $name = __('injongepay::injongepay.job_'.$key);

        audit('injongepay_background_job_failed', __('injongepay::injongepay.notify_job_failed_body', ['job' => $name]), null, ['job' => $key]);
        PlatformAlert::send('update injongepay_platform.setting', __('injongepay::injongepay.notify_job_failed_title'), __('injongepay::injongepay.notify_job_failed_body', ['job' => $name]),
            route('injongepay.platform.background-jobs', [], false));
    }
}
