<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use App\Services\Install\MailProbe;
use App\Services\Mail\MailSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Queue\Events\JobProcessing;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    private const DETAILS = [
        'host' => 'mail.acme.com', 'port' => 465, 'scheme' => 'smtps',
        'username' => 'no-reply@acme.com', 'password' => 'smtp-secret',
        'from_address' => 'no-reply@acme.com', 'from_name' => 'Acme',
    ];

    private function asSuperAdmin(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->withSession(['auth.staff_password_confirmed_at' => time()]);
    }

    private function probeWill(bool $ok): void
    {
        $probe = $this->mock(MailProbe::class);
        $probe->shouldReceive('send')->andReturn(['ok' => $ok, 'message' => $ok ? 'sent' : 'Could not send: refused']);
        $this->app->forgetInstance(MailSettings::class);
    }

    public function test_only_a_superadmin_can_open_or_change_it(): void
    {
        [, $owner] = $this->makeBusiness();
        $this->actingAs($owner, 'staff');
        $this->withSession(['auth.staff_password_confirmed_at' => time()]);

        $this->get(route('superadmin.settings.index', ['tab' => 'email']))->assertForbidden();
        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'save'])->assertForbidden();
        $this->delete(route('superadmin.settings.mail.reset'))->assertForbidden();
        $this->assertNull(System::getProperty(MailSettings::KEY));
    }

    public function test_saving_asks_for_the_password_first(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'save'])->assertRedirect();

        $this->assertNull(System::getProperty(MailSettings::KEY));
    }

    public function test_saved_details_are_used_for_sending_and_the_password_is_encrypted(): void
    {
        $this->asSuperAdmin();

        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'save'])
            ->assertRedirect(route('superadmin.settings.index', ['tab' => 'email']));

        $stored = System::getProperty(MailSettings::KEY);
        $this->assertStringNotContainsString('smtp-secret', $stored);
        $this->assertSame('smtp-secret', json_decode(Crypt::decryptString($stored), true)['password']);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.acme.com', config('mail.mailers.smtp.host'));
        $this->assertSame('no-reply@acme.com', config('mail.from.address'));

        $this->get(route('superadmin.settings.index', ['tab' => 'email']))
            ->assertOk()->assertSee('mail.acme.com')->assertDontSee('smtp-secret')
            ->assertSee(__('install.mail_scheme_auto'))->assertSeeInOrder(['value="smtps"', 'selected'], false);
    }

    public function test_a_failed_test_changes_nothing(): void
    {
        $this->asSuperAdmin();
        $this->probeWill(false);

        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'test', 'test_to' => 'me@acme.com'])
            ->assertSessionHas('error', 'Could not send: refused');

        $this->assertNull(System::getProperty(MailSettings::KEY));
        $this->assertTrue(ActivityLog::query()->where('action', 'mail_settings_test_failed')->exists());
    }

    public function test_a_passed_test_saves(): void
    {
        $this->asSuperAdmin();
        $this->probeWill(true);

        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'test', 'test_to' => 'me@acme.com'])
            ->assertSessionHas('success');

        $this->assertNotNull(System::getProperty(MailSettings::KEY));
    }

    public function test_an_empty_password_keeps_the_current_one_on_the_same_server(): void
    {
        $this->asSuperAdmin();
        $settings = app(MailSettings::class);
        $settings->save(self::DETAILS);

        $this->put(route('superadmin.settings.mail.update'), ['password' => '', 'port' => 587, 'action' => 'save'] + self::DETAILS);

        $this->assertSame('smtp-secret', $settings->saved()['password']);
        $this->assertSame(587, (int) $settings->saved()['port']);
    }

    public function test_the_current_password_is_never_sent_to_a_different_server(): void
    {
        $this->asSuperAdmin();
        $settings = app(MailSettings::class);
        $settings->save(self::DETAILS);

        // A test to another server would otherwise hand it the stored password.
        $this->mock(MailProbe::class)->shouldNotReceive('send');
        $this->app->forgetInstance(MailSettings::class);

        $this->put(route('superadmin.settings.mail.update'), ['host' => 'evil.example', 'password' => '', 'action' => 'test', 'test_to' => 'me@evil.example'] + self::DETAILS)
            ->assertSessionHas('error', __('body.mail_password_needed'));

        $this->assertSame('mail.acme.com', app(MailSettings::class)->saved()['host']);
    }

    public function test_the_password_stays_out_of_the_audit_log(): void
    {
        $this->asSuperAdmin();

        $this->put(route('superadmin.settings.mail.update'), self::DETAILS + ['action' => 'save']);

        $entry = ActivityLog::query()->where('action', 'mail_settings_changed')->firstOrFail();
        $this->assertSame('mail.acme.com', $entry->properties['changes']['host']['to']);
        $this->assertTrue($entry->properties['password_changed']);
        $this->assertStringNotContainsString('smtp-secret', ActivityLog::query()->get()->toJson());
    }

    public function test_going_back_to_the_server_file(): void
    {
        $this->asSuperAdmin();
        $before = [config('mail.default'), config('mail.mailers.smtp.host'), config('mail.from.address')];
        app(MailSettings::class)->save(self::DETAILS);

        $this->delete(route('superadmin.settings.mail.reset'))->assertSessionHas('success');

        $this->assertNull(System::getProperty(MailSettings::KEY));
        $this->assertSame($before, [config('mail.default'), config('mail.mailers.smtp.host'), config('mail.from.address')]);
        $this->assertTrue(ActivityLog::query()->where('action', 'mail_settings_reset')->exists());
    }

    public function test_a_running_worker_picks_up_a_change_before_its_next_job(): void
    {
        app('mail.manager')->mailer();
        $this->assertNotSame('mail.acme.com', config('mail.mailers.smtp.host'));

        // Saved by the web process, not this one.
        System::addProperty(MailSettings::KEY, Crypt::encryptString(json_encode(self::DETAILS)));
        event(new JobProcessing('redis', $this->createStub(\Illuminate\Contracts\Queue\Job::class)));

        $this->assertSame('mail.acme.com', config('mail.mailers.smtp.host'));
    }

    public function test_settings_saved_under_another_app_key_fall_back_to_the_server_file(): void
    {
        $this->asSuperAdmin();
        $mailer = config('mail.default');
        System::addProperty(MailSettings::KEY, 'not-readable');

        app(MailSettings::class)->apply();

        $this->assertSame($mailer, config('mail.default'));
        $this->get(route('superadmin.settings.index', ['tab' => 'email']))->assertOk()->assertSee(__('body.mail_unreadable'));
    }
}
