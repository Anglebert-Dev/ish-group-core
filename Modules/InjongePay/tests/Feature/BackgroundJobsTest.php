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
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\BackgroundJobs;
use Modules\InjongePay\Models\Wallet;

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

        return ['jobs' => $jobs, 'log_days' => BackgroundJobs::logDays()];
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

    public function test_a_job_killed_mid_run_frees_its_lock_within_the_hour(): void
    {
        $this->assertSame(60, $this->scheduled('injongepay:mirror')->expiresAt);
    }

    public function test_the_daily_sync_leaves_after_three_no_answers(): void
    {
        foreach (range(1, 6) as $i) {
            $this->wallet(overrides: ['name' => "Till {$i}", 'bank_status' => Wallet::BANK_ACTIVE]);
        }
        $this->fakeBank(['/merchants/lookup' => Http::response('', 504)]);

        $this->artisan('injongepay:sync-merchants --all')->assertSuccessful();

        Http::assertSentCount(4); // token + three lookups
    }

    public function test_the_cashout_check_leaves_after_three_no_answers_and_comes_back(): void
    {
        $wallet = $this->wallet();
        foreach (range(1, 5) as $i) {
            $cashout = new Cashout(['amount' => 100000]);
            $cashout->forceFill(['reference' => "CSH{$i}", 'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id, 'amount' => 100000, 'account' => '0001', 'account_holder' => 'X', 'status' => Cashout::UNKNOWN, 'submitted_at' => now()])->save();
        }
        $this->fakeBank(['/disbursement/status' => Http::response('', 504)]);

        $this->artisan('injongepay:check-cashouts')->assertSuccessful();

        $this->assertSame(3, Cashout::query()->withoutGlobalScopes()->whereNotNull('last_checked_at')->count());
        Http::assertSentCount(4);

        $this->artisan('injongepay:check-cashouts')->assertSuccessful();
        $this->assertSame(5, Cashout::query()->withoutGlobalScopes()->whereNotNull('last_checked_at')->count());
    }

    public function test_one_slow_answer_does_not_end_the_run(): void
    {
        foreach (range(1, 3) as $i) {
            $this->wallet(overrides: ['name' => "Till {$i}", 'bank_status' => Wallet::BANK_ACTIVE]);
        }
        $this->fakeBank(['/merchants/lookup' => Http::sequence()->push('', 504)->push('', 504)->push(['responseCode' => '01'])]);

        $this->artisan('injongepay:sync-merchants --all')->assertSuccessful();

        Http::assertSentCount(4);
    }

    public function test_old_call_logs_go_but_cashout_proof_stays(): void
    {
        $wallet = $this->wallet();
        $cashout = new Cashout;
        $cashout->forceFill(['reference' => 'CSH1', 'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id, 'amount' => 100000, 'account' => '0001', 'account_holder' => 'X', 'status' => Cashout::COMPLETED])->save();
        $call = fn (array $extra, $at) => tap(new BankCall($extra + ['direction' => 'outbound', 'method' => 'GET', 'endpoint' => '/x', 'outcome' => 'ok', 'duration_ms' => 1]), function ($c) use ($at) {
            $c->created_at = $at;
            $c->save();
        });

        $old = $call([], now()->subDays(91));
        $recent = $call([], now()->subDays(89));
        $proof = $call(['subject_type' => $cashout->getMorphClass(), 'subject_id' => $cashout->id], now()->subDays(400));
        $event = $call([], now()->subDays(400));
        DB::table('ijp_cashout_events')->insert(['cashout_id' => $cashout->id, 'to_status' => 'COMPLETED', 'source' => 'poller', 'bank_call_id' => $event->id, 'created_at' => now()]);

        $this->artisan('injongepay:prune-logs')->assertSuccessful();

        $this->assertDatabaseMissing('ijp_bank_calls', ['id' => $old->id]);
        foreach ([$recent, $proof, $event] as $kept) {
            $this->assertDatabaseHas('ijp_bank_calls', ['id' => $kept->id]);
        }
        $this->assertDatabaseHas('activity_logs', ['action' => 'injongepay_logs_pruned']);
    }

    public function test_retention_is_set_on_the_page_with_a_floor(): void
    {
        $this->asOperator();

        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), array_merge($this->form(), ['log_days' => 180]))->assertSessionHasNoErrors();
        $this->assertSame(180, BackgroundJobs::logDays());
        $this->assertDatabaseHas('activity_logs', ['action' => 'injongepay_log_retention_changed']);

        $this->withConfirmedPassword()->put(route('injongepay.platform.background-jobs.update'), array_merge($this->form(), ['log_days' => 5]))->assertSessionHasErrors('log_days');
    }
}
