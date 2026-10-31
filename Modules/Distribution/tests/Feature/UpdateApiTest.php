<?php

namespace Modules\Distribution\Tests\Feature;

use App\Services\Module\ModuleManager;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Enums\SiteModuleStatus;
use Modules\Distribution\Models\CheckIn;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\UpdateReport;
use Modules\Distribution\Services\ReleaseService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;

/**
 * Self-hosting phase 4 (docs/self-hosting-build-guide.md §7): the API
 * customer servers call.
 */
class UpdateApiTest extends TestCase
{
    protected string $publicKey;

    protected Site $site;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->makeSuperAdmin();

        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [$keys['public']]]);
        $this->publicKey = $keys['public'];
        Storage::fake('local');

        $this->site = app(SiteService::class)->create(['name' => 'Kigali Finance'], ['Savings', 'SalesOrders'])['data'];
        $this->token = app(SiteService::class)->issueToken($this->site);
    }

    // ── Authentication ───────────────────────────────────────────

    public function test_a_token_is_required_and_must_be_live(): void
    {
        $this->postJson(route('distribution.api.check-in'), $this->checkIn())->assertUnauthorized();
        $this->withToken('ish_nope')->postJson(route('distribution.api.check-in'), $this->checkIn())->assertUnauthorized();

        $this->site->tokens()->update(['revoked_at' => now()]);
        $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn())->assertUnauthorized();
    }

    public function test_a_suspended_site_or_a_wrong_uuid_is_refused(): void
    {
        $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn(['site_uuid' => 'someone-else']))->assertForbidden();

        $this->site->update(['status' => 'suspended']);
        $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn())->assertForbidden();
    }

    // ── Check-in ─────────────────────────────────────────────────

    public function test_the_answer_is_signed_and_echoes_the_nonce(): void
    {
        $response = $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn(['nonce' => 'n0nce-n0nce-n0nce-1']))->assertOk();

        $body = $response->getContent();
        $this->assertTrue(PackageSignature::verify(PackageSignature::responseMessage($body), $response->headers->get('X-Ish-Signature'), [$this->publicKey]));
        $this->assertFalse(PackageSignature::verify(PackageSignature::responseMessage($body.' '), $response->headers->get('X-Ish-Signature'), [$this->publicKey]));

        $response->assertJson(['site_uuid' => $this->site->uuid, 'nonce' => 'n0nce-n0nce-n0nce-1', 'update_mode' => 'manual', 'offers' => []]);
        $this->assertEqualsWithDelta(time(), $response->json('server_time'), 5);
    }

    public function test_check_in_records_what_the_site_reported(): void
    {
        $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn([
            'core_version' => 'abc1234', 'php_version' => '8.3.9', 'db_driver' => 'pgsql', 'app_url' => 'https://finance.example.com', 'modules_writable' => true,
            'modules' => [['name' => 'Savings', 'installed_version' => '1.1.0', 'enabled' => true]],
        ]))->assertOk();

        $site = $this->site->fresh();
        $this->assertSame('pgsql', $site->db_driver);
        $this->assertSame('https://finance.example.com', $site->app_url);
        $this->assertTrue($site->modules_writable);
        $this->assertNotNull($site->last_check_in_at);
        $this->assertSame('1.1.0', $site->modules()->where('module', 'Savings')->value('installed_version'));
        $this->assertSame(1, CheckIn::query()->count());
    }

    public function test_offers_newer_releases_and_missing_modules_requirements_first(): void
    {
        $this->release('Savings', '1.2.0');
        $this->release('Savings', '1.1.0');
        $this->release('Inventory', '1.0.0');
        $this->release('SalesOrders', '1.0.0');
        $this->release('Delivery', '9.0.0'); // not assigned: never offered

        $offers = $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn([
            'modules' => [['name' => 'Savings', 'installed_version' => '1.1.0'], ['name' => 'Inventory', 'installed_version' => '1.0.0']],
        ]))->assertOk()->json('offers');

        $this->assertSame(['SalesOrders', 'Savings'], array_column($offers, 'module'), 'Inventory up to date; SalesOrders after Inventory');
        $this->assertSame(['install', 'update'], array_column($offers, 'action'));
        $this->assertSame('1.2.0', $offers[1]['version']);
        $this->assertSame('1.1.0', $offers[1]['from_version']);
        $this->assertStringContainsString('/api/distribution/v1/releases/', $offers[1]['download_url']);

        $statuses = $this->site->modules()->pluck('status', 'module');
        $this->assertSame(SiteModuleStatus::UpdateAvailable, $statuses['Savings']);
        $this->assertSame(SiteModuleStatus::Installed, $statuses['Inventory']);
        $this->assertSame(SiteModuleStatus::PendingInstall, $statuses['SalesOrders']);
    }

    public function test_a_module_is_not_offered_while_something_it_needs_is_unreleased(): void
    {
        Release::query()->create(['module' => 'SalesOrders', 'version' => '1.0.0', 'sha256' => str_repeat('a', 64), 'signature' => 'sig', 'size' => 1, 'path' => 'x', 'requires' => ['Inventory'], 'published_at' => now()]);
        $check = fn () => array_column($this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn())->json('offers'), 'module');

        $this->assertNotContains('SalesOrders', $check(), 'Inventory is not installed and not released');

        $this->release('Inventory', '1.0.0');
        $this->assertSame(['Inventory', 'SalesOrders'], array_values(array_intersect($check(), ['Inventory', 'SalesOrders'])), 'offered together, requirement first');
    }

    public function test_withdrawn_and_older_releases_are_not_offered(): void
    {
        $this->release('Savings', '1.2.0')->update(['yanked_at' => now()]);
        $this->release('Savings', '1.0.0');

        $offers = $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn([
            'modules' => [['name' => 'Savings', 'installed_version' => '1.1.0']],
        ]))->json('offers');

        $this->assertNotContains('Savings', array_column($offers, 'module'));
    }

    // ── Download ─────────────────────────────────────────────────

    public function test_download_only_for_assigned_published_releases(): void
    {
        $savings = app(ReleaseService::class)->publish('Savings')['data'];
        $delivery = app(ReleaseService::class)->publish('Delivery')['data'];

        $this->withToken($this->token)->get(route('distribution.api.download', $savings))->assertOk()->assertHeader('X-Ish-Sha256', $savings->sha256);
        $this->withToken($this->token)->getJson(route('distribution.api.download', $delivery))->assertNotFound();
        $this->withoutToken()->getJson(route("distribution.api.download", $savings))->assertUnauthorized();

        $savings->update(['yanked_at' => now()]);
        $this->withToken($this->token)->getJson(route('distribution.api.download', $savings))->assertNotFound();
    }

    // ── Report ───────────────────────────────────────────────────

    public function test_reports_update_the_sites_modules(): void
    {
        $this->withToken($this->token)->postJson(route('distribution.api.report'), [
            'site_uuid' => $this->site->uuid,
            'results' => [
                ['module' => 'Savings', 'action' => 'update', 'from_version' => '1.1.0', 'to_version' => '1.2.0', 'status' => 'success', 'duration_ms' => 1200],
                ['module' => 'SalesOrders', 'action' => 'install', 'to_version' => '1.0.0', 'status' => 'rolled_back', 'error' => 'Migration failed'],
            ],
        ])->assertOk();

        $rows = $this->site->modules()->get()->keyBy('module');
        $this->assertSame('1.2.0', $rows['Savings']->installed_version);
        $this->assertSame(SiteModuleStatus::Installed, $rows['Savings']->status);
        $this->assertSame(SiteModuleStatus::Failed, $rows['SalesOrders']->status);
        $this->assertSame('Migration failed', $rows['SalesOrders']->last_error);
        $this->assertSame(2, UpdateReport::query()->count());

        $this->withToken($this->token)->postJson(route('distribution.api.report'), ['site_uuid' => $this->site->uuid, 'results' => [['module' => 'Savings']]])
            ->assertUnprocessable();
    }

    public function test_the_answer_lists_the_sites_modules(): void
    {
        $assigned = $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn())->assertOk()->json('assigned');

        $this->assertEqualsCanonicalizing(['Savings', 'SalesOrders', 'Inventory'], $assigned);
    }

    public function test_we_are_told_when_a_site_switched_a_module_off_or_a_new_one_on(): void
    {
        NotificationFacade::fake();

        $this->withToken($this->token)->postJson(route('distribution.api.report'), [
            'site_uuid' => $this->site->uuid,
            'results' => [
                ['module' => 'Delivery', 'action' => 'disable', 'from_version' => '1.0.0', 'to_version' => '1.0.0', 'status' => 'success'],
                ['module' => 'SalesOrders', 'action' => 'install', 'to_version' => '1.1.0', 'status' => 'success'],
                ['module' => 'Savings', 'action' => 'update', 'from_version' => '1.1.0', 'to_version' => '1.1.1', 'status' => 'success'],
            ],
        ])->assertOk();

        $titles = \App\Models\Notification\Notification::query()->pluck('title')->all();
        $this->assertContains('Kigali Finance switched off Delivery', $titles);
        $this->assertContains('Kigali Finance installed SalesOrders 1.1.0', $titles);
        $this->assertCount(2, $titles, 'a plain successful update is not announced');
        NotificationFacade::assertSentTimes(\App\Notifications\BrandedNotification::class, 2);
    }

    public function test_a_given_module_without_a_release_says_so_on_the_site_page(): void
    {
        $this->actingAs($this->makeSuperAdmin(['email' => 'root2@example.com']), 'staff');

        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()
            ->assertSee(__('distribution::distribution.not_released_yet'))
            ->assertSee(__('distribution::distribution.publish_now'))
            ->assertSee(__('distribution::distribution.not_reported_yet'))
            ->assertSee(__('distribution::distribution.zip_contacts'));

        // Installed from the zip: no warning, nothing to publish.
        $this->site->modules()->update(['installed_version' => '1.0.0', 'status' => 'installed']);
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()
            ->assertSee(__('distribution::distribution.no_release_needed_hint'))
            ->assertDontSee(__('distribution::distribution.not_released_yet'));
    }

    public function test_site_page_shows_history(): void
    {
        $this->withToken($this->token)->postJson(route('distribution.api.report'), [
            'site_uuid' => $this->site->uuid,
            'results' => [['module' => 'Savings', 'action' => 'update', 'from_version' => '1.1.0', 'to_version' => '1.2.0', 'status' => 'success']],
        ])->assertOk();

        $this->actingAs($this->makeSuperAdmin(['email' => 'root2@example.com']), 'staff');
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()->assertSee('1.1.0 → 1.2.0');
    }

    public function test_releases_for_test_sites_first_reach_only_test_sites_until_released_to_everyone(): void
    {
        $other = app(SiteService::class)->create(['name' => 'Pilot', 'is_test' => true], ['Savings'])['data'];
        $pilotToken = app(SiteService::class)->issueToken($other);
        $release = app(ReleaseService::class)->publish('Savings', 'try me', testOnly: true)['data'];
        $installed = ['modules' => [['name' => 'Savings', 'installed_version' => '0.9.0']]];

        $offered = fn (string $token, Site $site) => array_column($this->withToken($token)->postJson(route('distribution.api.check-in'), $this->checkIn(['site_uuid' => $site->uuid] + $installed))->json('offers'), 'module');

        $this->assertContains('Savings', $offered($pilotToken, $other), 'test site gets it');
        $this->assertNotContains('Savings', $offered($this->token, $this->site), 'others wait');
        $this->withToken($this->token)->getJson(route('distribution.api.download', $release))->assertNotFound();

        $this->actingAs($this->makeSuperAdmin(['email' => 'root3@example.com']), 'staff');
        $this->get(route('superadmin.distribution.releases.index'))->assertSee(__('distribution::distribution.test_sites_only'))->assertSee(__('distribution::distribution.publish_test_first'));
        $this->post(route('superadmin.distribution.releases.everyone', $release))->assertSessionHas('success');

        $this->assertContains('Savings', $offered($this->token, $this->site), 'everyone now');
    }

    public function test_a_link_key_reconnects_a_site_and_can_retire_old_tokens(): void
    {
        NotificationFacade::fake();
        $this->actingAs($this->makeSuperAdmin(['email' => 'root4@example.com']), 'staff');

        $this->withConfirmedPassword()->post(route('superadmin.distribution.sites.link-key', $this->site), ['email' => 'it@acme.test', 'revoke_others' => '1'])
            ->assertSessionHas('success')
            ->assertSessionHas('link_key');

        $link = \App\Support\Distribution\LinkKey::decode(session('link_key'));
        $this->assertSame($this->site->uuid, $link['site_uuid']);

        $this->withToken($this->token)->postJson(route('distribution.api.check-in'), $this->checkIn())->assertUnauthorized();
        $this->withToken($link['token'])->postJson(route('distribution.api.check-in'), $this->checkIn())->assertOk();

        NotificationFacade::assertSentOnDemand(\Modules\Distribution\Notifications\CustomerEmail::class, fn ($mail) => str_contains(implode(' ', $mail->lines), session('link_key')));
        $this->assertTrue(\App\Models\ActivityLog\ActivityLog::query()->where('action', 'distribution_link_key_issued')->exists());
    }

    private function checkIn(array $overrides = []): array
    {
        return $overrides + [
            'site_uuid' => $this->site->uuid,
            'nonce' => bin2hex(random_bytes(16)),
            'core_version' => 'dev',
            'modules' => [],
        ];
    }

    private function release(string $module, string $version): Release
    {
        return Release::query()->create([
            'module' => $module, 'version' => $version, 'sha256' => str_repeat('a', 64), 'signature' => 'sig',
            'size' => 10, 'path' => "x/{$module}-{$version}.zip", 'published_at' => now(),
        ]);
    }
}
