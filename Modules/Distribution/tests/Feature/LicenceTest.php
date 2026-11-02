<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Enums\ChargeStatus;
use Modules\Distribution\Models\Charge;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Notifications\CustomerEmail;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\LicenceService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Modules\Distribution\Tests\Feature\Concerns\PaidSite;
use Tests\TestCase;

/**
 * Licences, support & updates, reminders (docs/self-hosting-build-guide.md §11).
 */
class LicenceTest extends TestCase
{
    use PaidSite;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $super = $this->makeSuperAdmin();
        System::setProperty('primary_business_id', $super->business_id);
        $this->actingAs($super, 'staff');
        Storage::fake('local');
        Notification::fake();

        $this->site = app(SiteService::class)->create(['name' => 'Kigali Finance', 'customer_name' => 'Kigali Finance Ltd', 'contact_email' => 'it@kf.test'], ['Savings'])['data'];
    }

    public function test_nothing_ships_or_updates_before_the_licence_is_paid(): void
    {
        $this->post(route('superadmin.distribution.sites.store'), ['name' => 'Acme', 'update_mode' => 'manual', 'status' => 'active', 'modules' => ['Savings']])->assertRedirect();
        $site = Site::query()->where('name', 'Acme')->firstOrFail();

        $this->assertNull($site->support_until, 'support starts at payment, not at creation');
        $this->assertFalse($site->hasSupport());
        $this->assertFalse($site->licencePaid());
        $this->get(route('superadmin.distribution.sites.show', $site))->assertOk()->assertSee(__('distribution::distribution.licence_unpaid_title'));

        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [$keys['public']]]);
        $refused = app(BuildService::class)->start($site);
        $this->assertFalse($refused['status']);
        $this->assertSame(__('distribution::distribution.build_needs_paid_licence'), $refused['message']);

        // Paid: the zip can be built and a year of support begins.
        $charge = app(LicenceService::class)->charge($site, ['kind' => 'licence', 'amount' => 500000, 'support_months' => 12])['data'];
        app(LicenceService::class)->markPaid($charge, ['paid_on' => today()->toDateString()]);
        $site = $site->fresh();
        $this->assertTrue($site->licencePaid());
        $this->assertTrue($site->hasSupport());
        $this->assertTrue(app(BuildService::class)->start($site)['status']);

        // Test sites need no licence.
        $this->assertTrue((new Site(['is_test' => true]))->hasSupport());
    }

    public function test_licence_charge_prints_emails_and_paying_extends_support(): void
    {
        $this->site->forceFill(['support_until' => null])->save();

        $this->post(route('superadmin.distribution.sites.charges.store', $this->site), ['kind' => 'licence', 'amount' => '1500000', 'support_months' => 12, 'due_days' => 14])->assertRedirect();
        $charge = Charge::query()->firstOrFail();
        $this->assertStringStartsWith('LIC-', $charge->number);
        $this->assertStringContainsString('Savings', $charge->description);
        $this->assertSame(today()->addMonthsNoOverflow(12)->subDay()->toDateString(), $charge->period_until->toDateString());

        $this->get(route('print.show', ['distribution_charge', $charge->uuid]))->assertOk()->assertSee($charge->number)->assertSee('Kigali Finance Ltd');
        $this->get(route('print.pdf', ['distribution_charge', $charge->uuid]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('superadmin.distribution.sites.charges.email', [$this->site, $charge]), ['email' => 'it@kf.test'])->assertRedirect();
        Notification::assertSentOnDemand(CustomerEmail::class, fn ($mail) => str_starts_with(base64_decode($mail->attachments[$charge->number.'.pdf']), '%PDF'));

        $this->post(route('superadmin.distribution.sites.charges.pay', [$this->site, $charge]), ['paid_on' => today()->toDateString(), 'payment_method' => 'bank', 'payment_reference' => 'BK-1'])->assertRedirect();
        $this->assertSame(ChargeStatus::Paid, $charge->fresh()->status);
        $this->assertSame($charge->period_until->toDateString(), $this->site->fresh()->support_until->toDateString());
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_charge_paid')->exists());

        // A renewal starts the day after the current period.
        $this->post(route('superadmin.distribution.sites.charges.store', $this->site), ['kind' => 'support', 'amount' => '300000', 'support_months' => 12]);
        $renewal = Charge::query()->where('kind', 'support')->firstOrFail();
        $this->assertSame($charge->period_until->copy()->addDay()->toDateString(), $renewal->period_from->toDateString());
        $this->post(route('superadmin.distribution.sites.charges.pay', [$this->site, $renewal]), ['paid_on' => today()->toDateString()]);
        $this->assertSame($renewal->period_until->toDateString(), $this->site->fresh()->support_until->toDateString());

        // Paid twice? No.
        $this->post(route('superadmin.distribution.sites.charges.pay', [$this->site, $renewal]), ['paid_on' => today()->toDateString()])->assertSessionHas('error');
    }

    public function test_an_unpaid_charge_is_corrected_in_place_and_licences_are_not_duplicated(): void
    {
        $this->post(route('superadmin.distribution.sites.charges.store', $this->site), ['kind' => 'licence', 'amount' => '500000', 'support_months' => 0])->assertSessionHas('success');
        $charge = Charge::query()->firstOrFail();
        $this->assertStringNotContainsString('0 months', $charge->description);

        // A second licence while one is open: edit that one instead.
        $this->post(route('superadmin.distribution.sites.charges.store', $this->site), ['kind' => 'licence', 'amount' => '450000', 'support_months' => 12])->assertSessionHas('error');
        $this->assertSame(1, Charge::query()->count());

        $this->put(route('superadmin.distribution.sites.charges.update', [$this->site, $charge]), ['amount' => '450000', 'support_months' => 12, 'due_on' => today()->addDays(30)->toDateString()])
            ->assertSessionHas('success');
        $charge = $charge->fresh();
        $this->assertSame('450000.00', $charge->amount);
        $this->assertSame(12, LicenceService::monthsOf($charge));
        $this->assertStringContainsString('12 months', $charge->description);
        // The invoice's history shows what the edit changed.
        $edit = ActivityLog::query()->where('action', 'distribution_charge_updated')->firstOrFail();
        $this->assertSame(['from' => '500000.00', 'to' => '450000.00'], $edit->properties['changes']['amount']);
        $this->get(route('superadmin.distribution.sites.show', ['site' => $this->site, 'tab' => 'licence']))->assertOk()
            ->assertSee(__('distribution::distribution.history_title'))->assertSee(__('distribution::distribution.event_distribution_charge_updated'));

        // Paid charges are records: no more changes.
        app(LicenceService::class)->markPaid($charge, ['paid_on' => today()->toDateString()]);
        $this->put(route('superadmin.distribution.sites.charges.update', [$this->site, $charge]), ['amount' => '1'])->assertSessionHas('error');
        $this->assertSame('450000.00', $charge->fresh()->amount);
    }

    public function test_one_licence_per_site_and_renewals_only_after_it_is_paid(): void
    {
        $licences = app(LicenceService::class);

        $early = $licences->charge($this->site, ['kind' => 'support', 'amount' => 300000, 'support_months' => 12]);
        $this->assertFalse($early['status']);
        $this->assertSame(__('distribution::distribution.renewal_needs_licence'), $early['message']);

        $licence = $licences->charge($this->site, ['kind' => 'licence', 'amount' => 500000, 'support_months' => 12])['data'];
        $licences->markPaid($licence, ['paid_on' => today()->toDateString()]);

        $again = $licences->charge($this->site->fresh(), ['kind' => 'licence', 'amount' => 500000]);
        $this->assertFalse($again['status']);
        $this->assertSame(__('distribution::distribution.licence_already_paid'), $again['message']);

        $this->assertTrue($licences->charge($this->site->fresh(), ['kind' => 'support', 'amount' => 300000, 'support_months' => 12])['status']);
        $this->assertFalse($licences->charge($this->site->fresh(), ['kind' => 'support', 'amount' => 300000, 'support_months' => 12])['status'], 'one open renewal at a time');
    }

    public function test_the_page_shows_each_step_and_a_test_site_says_it_needs_no_licence(): void
    {
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()
            ->assertSee(__('distribution::distribution.steps_title'))
            ->assertSee(__('distribution::distribution.step_licence_none'))
            ->assertDontSee(__('distribution::distribution.test_site_banner_title'));

        $this->site->forceFill(['is_test' => true])->save();
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()
            ->assertSee(__('distribution::distribution.test_site_banner_title'))
            ->assertSee(__('distribution::distribution.step_licence_test'));
    }

    public function test_other_businesses_cannot_print_our_charges(): void
    {
        $charge = app(LicenceService::class)->charge($this->site, ['kind' => 'licence', 'amount' => 10])['data'];
        $this->flushSession();
        $this->actingAs($this->makeStaff(), 'staff');

        $this->get(route('print.show', ['distribution_charge', $charge->uuid]))->assertNotFound();
    }

    public function test_after_support_ends_no_updates_but_nothing_is_taken_away(): void
    {
        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [$keys['public']]]);
        $token = app(SiteService::class)->issueToken($this->site);
        $release = Release::query()->create(['module' => 'Savings', 'version' => '2.0.0', 'sha256' => str_repeat('a', 64), 'signature' => 's', 'size' => 1, 'path' => 'x/s.zip', 'published_at' => now()]);
        $body = ['site_uuid' => $this->site->uuid, 'nonce' => bin2hex(random_bytes(16)), 'core_version' => 'dev', 'modules' => [['name' => 'Savings', 'installed_version' => '1.0.0']]];

        $this->site->forceFill(['support_until' => today()->addDays(3)])->save();
        $this->withToken($token)->postJson(route('distribution.api.check-in'), $body)->assertOk()
            ->assertJsonPath('offers.0.version', '2.0.0')->assertJsonPath('support.active', true);

        $this->site->forceFill(['support_until' => today()->subDay()])->save();
        $answer = $this->withToken($token)->postJson(route('distribution.api.check-in'), ['nonce' => bin2hex(random_bytes(16))] + $body)->assertOk();
        $this->assertSame([], $answer->json('offers'));
        $this->assertFalse($answer->json('support.active'));
        $this->assertSame(['Savings'], $answer->json('assigned'), 'modules stay: the app keeps working');
        $this->withToken($token)->get(route('distribution.api.download', $release))->assertForbidden();
    }

    public function test_reminders_go_once_per_stage_and_start_over_when_extended(): void
    {
        $licences = app(LicenceService::class);
        $this->site->forceFill(['support_until' => today()->addDays(30)])->save();

        $this->assertSame(1, $licences->sendDueReminders());
        $this->assertSame(0, $licences->sendDueReminders(), 'the 30-day reminder goes once');
        Notification::assertSentOnDemand(CustomerEmail::class, fn ($mail) => str_contains($mail->subject, 'end on'));

        $this->travel(23)->days();
        $this->assertSame(7, $licences->dueStage($this->site->fresh()));
        $this->assertSame(1, $licences->sendDueReminders());

        $this->travel(8)->days();
        $this->assertSame(0, $licences->dueStage($this->site->fresh()));
        $this->assertSame(1, $licences->sendDueReminders());
        $this->assertSame(0, $licences->sendDueReminders());
        Notification::assertSentOnDemand(CustomerEmail::class, fn ($mail) => str_contains($mail->subject, 'have ended'));

        // Renewed: a new end date, its reminders start over.
        $licences->setSupportUntil($this->site->fresh(), today()->addDays(20)->toDateString(), 'renewed by phone');
        $this->assertSame(30, $licences->dueStage($this->site->fresh()));
    }

    public function test_the_reminder_carries_an_open_renewal_invoice(): void
    {
        $this->paid($this->site)->forceFill(['support_until' => today()->addDays(5)])->save();
        $renewal = app(LicenceService::class)->charge($this->site, ['kind' => 'support', 'amount' => 300000, 'support_months' => 12])['data'];

        $this->post(route('superadmin.distribution.sites.support.remind', $this->site))->assertRedirect()->assertSessionHas('success');
        Notification::assertSentOnDemand(CustomerEmail::class, fn ($mail) => array_key_exists($renewal->number.'.pdf', $mail->attachments));
        $this->artisan('distribution:support-reminders')->assertSuccessful();
    }

    public function test_changing_the_date_by_hand_needs_a_reason_and_is_audited(): void
    {
        $this->withConfirmedPassword();

        $this->put(route('superadmin.distribution.sites.support', $this->site), ['support_until' => '2028-01-31'])->assertSessionHasErrors('reason');
        $this->put(route('superadmin.distribution.sites.support', $this->site), ['support_until' => '2028-01-31', 'reason' => 'Goodwill: outage in March'])->assertRedirect();

        $this->assertSame('2028-01-31', $this->site->fresh()->support_until->toDateString());

        // "No support (until paid)": the date goes, whatever was typed.
        $this->put(route('superadmin.distribution.sites.support', $this->site), ['support_until' => '2028-01-31', 'no_support' => '1', 'reason' => 'Licence not paid yet'])->assertRedirect();
        $this->assertNull($this->site->fresh()->support_until);
        $this->assertFalse($this->site->fresh()->hasSupport());
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_support_changed')->where('description', 'like', '%Goodwill%')->exists());
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()->assertSee(__('distribution::distribution.licence_title'));
    }
}
