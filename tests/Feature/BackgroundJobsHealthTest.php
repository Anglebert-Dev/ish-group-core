<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Support\Health\BackgroundJobs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Superadmins are told when nothing sends queued emails or runs the scheduler.
 */
class BackgroundJobsHealthTest extends TestCase
{
    public function test_superadmins_see_waiting_emails_and_a_stopped_scheduler(): void
    {
        config(['queue.default' => 'database', 'queue.via_scheduler' => false]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()]);

        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee(trans_choice('body.health_queue_stuck', 1, ['count' => 1, 'minutes' => 5]))
            ->assertSee('php artisan queue:work --tries=3')
            ->assertSee(__('body.health_scheduler_stopped'));
    }

    public function test_all_quiet_when_the_workers_run(): void
    {
        config(['queue.default' => 'database']);
        System::addProperty(BackgroundJobs::HEARTBEAT, now()->toIso8601String());
        Cache::forget('health.background');

        $this->assertSame([], BackgroundJobs::problems());

        // Ordinary staff never see it.
        $this->actingAsStaff();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(__('body.health_scheduler_stopped'));
    }

    public function test_the_scheduler_beats_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('scheduler-heartbeat')->assertSuccessful();
    }
}
