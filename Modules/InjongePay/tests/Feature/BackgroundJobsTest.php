<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\System\System;
use App\Notifications\BrandedNotification;
use App\Support\Health\BackgroundJobs as Health;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\InjongePay\Services\BackgroundJobs;

class BackgroundJobsTest extends InjongePayTestCase
{
    private function jobs(): BackgroundJobs
    {
        return app(BackgroundJobs::class);
    }

    private function job(string $key): array
    {
        return collect($this->jobs()->all())->firstWhere('key', $key);
    }

    private function form(array $overrides = []): array
    {
        $jobs = [];
        foreach ($this->jobs()->all() as $job) {
            $jobs[$job['key']] = ($overrides[$job['key']] ?? []) + ['enabled' => 1, 'timing' => $job['timing']];
        }

        return ['jobs' => $jobs];
    }

    private function scheduled(string $command): \Illuminate\Console\Scheduling\Event
    {
        $schedule = new Schedule;
        $this->jobs()->register($schedule);

        return collect($schedule->events())->first(fn ($e) => str_ends_with(rtrim($e->command, ' '), $command) || str_contains($e->command, $command.' '));
    }

    public function test_without_settings_the_jobs_keep_their_usual_times(): void
    {
        $this->assertSame('*/10 * * * *', $this->scheduled('injongepay:mirror')->expression);
        $this->assertSame('*/1 * * * *', $this->jobs()->cron('process_inbox'));
        $this->assertSame('30 1 * * *', $this->scheduled('injongepay:close-day')->expression);
    }

    public function test_inhills_changes_a_jobs_timing_from_the_page_and_the_schedule_follows(): void
    {
        $this->asOperator();
        $this->get(route('injongepay.platform.background-jobs'))->assertOk()->assertSee(__('injongepay::injongepay.job_mirror'));

        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), $this->form([
            'mirror' => ['timing' => '30'], 'close_day' => ['timing' => '03:15'],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('*/30 * * * *', $this->scheduled('injongepay:mirror')->expression);
        $this->assertSame('15 3 * * *', $this->scheduled('injongepay:close-day')->expression);
        $this->assertDatabaseHas('activity_logs', ['action' => 'injongepay_background_jobs_changed']);
    }

    public function test_a_job_switched_off_does_not_run_and_says_so(): void
    {
        $this->asOperator();
        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), $this->form(['mirror' => ['enabled' => 0]]))->assertSessionHasNoErrors();

        $this->assertFalse($this->scheduled('injongepay:mirror')->filtersPass(app()));
        $this->assertTrue($this->scheduled('injongepay:sweep-fees')->filtersPass(app()));
        $this->assertSame('off', $this->job('mirror')['status']);
    }

    public function test_a_timing_that_is_not_offered_is_refused(): void
    {
        $this->asOperator();

        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), $this->form(['mirror' => ['timing' => '7']]))->assertSessionHasErrors('jobs.mirror.timing');
        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), $this->form(['close_day' => ['timing' => '25:00']]))->assertSessionHasErrors('jobs.close_day.timing');
    }

    public function test_status_follows_what_really_happened(): void
    {
        $this->assertSame('never', $this->job('mirror')['status']);

        $this->jobs()->started('mirror');
        $this->assertSame('running', $this->job('mirror')['status']);

        $this->jobs()->finished('mirror', true);
        $this->assertSame('ok', $this->job('mirror')['status']);
        $this->assertNotNull($this->job('mirror')['last_finished_at']);

        $this->travel(31)->minutes();
        $this->assertSame('late', $this->job('mirror')['status']);

        $this->jobs()->started('close_day');
        $this->travel(40)->minutes();
        $this->assertSame('late', $this->job('close_day')['status']);
    }

    public function test_a_failure_is_told_once_and_clears_when_it_works_again(): void
    {
        Notification::fake();

        $this->jobs()->started('mirror');
        $this->jobs()->finished('mirror', false);
        $this->jobs()->started('mirror');
        $this->assertSame('failed', $this->job('mirror')['status']);
        $this->jobs()->finished('mirror', false);

        Notification::assertSentToTimes($this->operatorAdmin, BrandedNotification::class, 1);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'injongepay_background_job_failed')->count());

        $this->jobs()->finished('mirror', true);
        $this->assertSame('ok', $this->job('mirror')['status']);
    }

    public function test_gtbank_not_answering_counts_as_a_failed_run(): void
    {
        $this->fakeBank(['/participant/transactions' => Http::response('', 504)]);

        $this->artisan('injongepay:mirror')->assertFailed();
        $this->artisan('injongepay:close-day', ['day' => '2026-11-02'])->assertFailed();
    }

    public function test_inhills_sees_a_banner_on_every_page_and_a_business_never_does(): void
    {
        $this->jobs()->finished('mirror', false);

        $this->asOperator();
        $this->get(route('injongepay.platform.wallets.index'))->assertSee(__('injongepay::injongepay.background_jobs_attention'));

        $this->flushSession();
        $this->asOwner();
        $this->get(route('injongepay.payments.index'))->assertDontSee(__('injongepay::injongepay.background_jobs_attention'));
        $this->get(route('injongepay.platform.background-jobs'))->assertForbidden();
    }

    public function test_the_page_shows_when_the_scheduler_has_stopped(): void
    {
        Cache::forget('health.background');
        System::addProperty(Health::HEARTBEAT, now()->subHour()->toIso8601String());
        $this->asOperator();

        $this->get(route('injongepay.platform.background-jobs'))->assertOk()->assertSee(__('injongepay::injongepay.jobs_needs_action'))->assertSee('schedule:');
    }
}
