<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use App\Services\Module\ModuleManager;
use App\Services\Updates\ModulePackageInstaller;
use App\Services\Updates\SafeZipExtractor;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateService;
use App\Support\Distribution\PackageSignature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Self-hosting phase 5 (docs/self-hosting-build-guide.md §5.2): a customer
 * server checks our update server, verifies, installs, rolls back.
 *
 * Uses a throwaway fixture module "UpdProbe" created under Modules/.
 */
class ModuleUpdatesTest extends TestCase
{
    private const SERVER = 'https://updates.example.com';

    private string $secret;

    private string $tmp;

    /** What the faked server answers right now (read at each request). */
    private ?array $plan = null;

    private array $liveOffers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        config([
            'distribution.public_keys' => [base64_encode(sodium_crypto_sign_publickey($pair))],
            'distribution.update_in_process' => true, // the test database lives in this process
            'distribution.backup_before_update' => false,
        ]);

        System::addProperty('distribution_server_url', self::SERVER);
        System::addProperty('distribution_site_uuid', 'site-1');
        System::addProperty('distribution_token', Crypt::encryptString('ish_secret'));
        System::addProperty('primary_business_id', $this->makeSuperAdmin(['email' => 'root@example.com'])->business_id);

        $this->tmp = sys_get_temp_dir().'/ish-updates-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        foreach (['UpdProbe', 'UpdProbeTwo'] as $module) {
            File::deleteDirectory(base_path('Modules/'.$module));
        }
        File::deleteDirectory(storage_path('app/updates'));
        File::deleteDirectory($this->tmp);
        app(ModuleManager::class)->rescan();

        parent::tearDown();
    }

    // ── Check-in ─────────────────────────────────────────────────

    public function test_check_remembers_offers_and_mode_from_a_signed_answer(): void
    {
        $this->fakeServer(offers: [$this->offer('UpdProbe', '1.1.0', 'update')], mode: 'auto');

        $result = app(UpdateService::class)->check();

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame('auto', System::getProperty('distribution_update_mode'));
        $this->assertSame(['UpdProbe'], array_column(app(UpdateService::class)->offers(), 'module'));

        Http::assertSent(fn (Request $request) => $request->url() === self::SERVER.'/api/distribution/v1/check-in'
            && $request->hasHeader('Authorization', 'Bearer ish_secret')
            && $request['site_uuid'] === 'site-1'
            && isset($request['modules'], $request['php_version']));
    }

    public function test_an_answer_signed_by_someone_else_is_refused(): void
    {
        $this->assertRefused('bad_signature', forge: true);
    }

    public function test_an_answer_to_another_request_is_refused(): void
    {
        $this->assertRefused('bad_answer', nonce: 'not-the-one-we-sent');
    }

    public function test_a_stale_answer_is_refused(): void
    {
        $this->assertRefused('clock_skew', time: time() - 3600);
    }

    public function test_offers_pointing_elsewhere_or_at_billing_are_dropped(): void
    {
        $elsewhere = ['download_url' => 'https://evil.example.com/x.zip'] + $this->offer('UpdProbe', '1.1.0', 'update');
        $this->fakeServer(offers: [$elsewhere, $this->offer('Billing', '9.0.0', 'install'), $this->offer('UpdProbeTwo', '1.0.0', 'install')]);

        app(UpdateService::class)->check();

        $this->assertSame(['UpdProbeTwo'], array_column(app(UpdateService::class)->offers(), 'module'));
    }

    // ── Safe extraction ──────────────────────────────────────────

    public function test_unsafe_packages_are_refused(): void
    {
        $cases = [
            'traversal' => ['UpdProbe/../../evil.php' => 'x'],
            'absolute' => ['/etc/evil' => 'x'],
            'other folder' => ['UpdProbe/module.json' => '{}', 'Other/evil.php' => 'x'],
            'backslash' => ['UpdProbe\\evil.php' => 'x'],
        ];

        foreach ($cases as $label => $files) {
            $zip = $this->zip($files);

            try {
                app(SafeZipExtractor::class)->extract($zip, 'UpdProbe', $this->tmp.'/out-'.md5($label));
                $this->fail("{$label} should be refused");
            } catch (UpdateException) {
                $this->assertFileDoesNotExist($this->tmp.'/evil.php');
            }
        }

        $symlink = $this->zip(['UpdProbe/module.json' => '{}', 'UpdProbe/link' => '/etc/passwd'], symlink: 'UpdProbe/link');
        $this->expectException(UpdateException::class);
        app(SafeZipExtractor::class)->extract($symlink, 'UpdProbe', $this->tmp.'/out-symlink');
    }

    // ── Installing ───────────────────────────────────────────────

    public function test_an_update_replaces_the_folder_and_runs_new_migrations(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0', ['2026_11_01_000001_create_upd_probe_notes.php' => $this->migration('upd_probe_notes')]);

        $result = app(ModulePackageInstaller::class)->install($offer, $zip);

        $this->assertSame('success', $result['status'], (string) $result['error']);
        $this->assertSame('1.0.0', $result['from_version']);
        $this->assertSame('1.1.0', app(ModuleManager::class)->installedVersion('UpdProbe'));
        $this->assertSame('1.1.0', json_decode(File::get(base_path('Modules/UpdProbe/module.json')), true)['version']);
        $this->assertTrue(Schema::hasTable('upd_probe_notes'));
        $this->assertCount(1, File::directories(storage_path('app/updates/backups')), 'old version kept');
    }

    public function test_a_failing_migration_is_rolled_back_and_the_old_folder_restored(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0', [
            '2026_11_01_000001_create_upd_probe_ok.php' => $this->migration('upd_probe_ok'),
            '2026_11_01_000002_break.php' => $this->migration('upd_probe_never', fail: true),
        ]);

        $result = app(ModulePackageInstaller::class)->install($offer, $zip);

        $this->assertSame('rolled_back', $result['status']);
        $this->assertStringContainsString('boom', (string) $result['error']);
        $this->assertSame('1.0.0', app(ModuleManager::class)->installedVersion('UpdProbe'));
        $this->assertSame('1.0.0', json_decode(File::get(base_path('Modules/UpdProbe/module.json')), true)['version']);
        $this->assertFalse(Schema::hasTable('upd_probe_ok'), 'migrations of the failed update undone');
        $this->assertFalse(Schema::hasTable('upd_probe_never'));
    }

    public function test_tampered_packages_change_nothing(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0');

        $wrongHash = app(ModulePackageInstaller::class)->install(['sha256' => str_repeat('0', 64)] + $offer, $zip);
        $wrongSignature = app(ModulePackageInstaller::class)->install(['signature' => base64_encode(str_repeat('x', 64))] + $offer, $zip);
        $wrongVersion = app(ModulePackageInstaller::class)->install(['version' => '1.2.0', 'signature' => $this->sign('UpdProbe', '1.2.0', $offer['sha256'])] + $offer, $zip);

        $this->assertSame(__('updates.hash_mismatch'), $wrongHash['error']);
        $this->assertSame(__('updates.bad_package_signature'), $wrongSignature['error']);
        $this->assertSame(__('updates.manifest_mismatch'), $wrongVersion['error']);
        $this->assertSame('1.0.0', app(ModuleManager::class)->installedVersion('UpdProbe'));
    }

    public function test_a_newly_given_module_is_installed(): void
    {
        [$offer, $zip] = $this->package('1.0.0', [], 'UpdProbeTwo', 'install');

        $result = app(ModulePackageInstaller::class)->install($offer, $zip);

        $this->assertSame('success', $result['status'], (string) $result['error']);
        $this->assertTrue(app(ModuleManager::class)->isActive('UpdProbeTwo'));
    }

    // ── The whole run ────────────────────────────────────────────

    public function test_automatic_sites_install_on_check_and_report_back(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0');
        $this->fakeServer(offers: [$offer], mode: 'auto', downloads: [$offer['download_url'] => File::get($zip)]);

        $this->artisan('core:updates')->assertSuccessful();

        $this->assertSame('1.1.0', app(ModuleManager::class)->installedVersion('UpdProbe'));
        $this->assertSame([], app(UpdateService::class)->offers(), 'installed offers are cleared');
        $this->assertSame('success', app(UpdateService::class)->history()[0]['status']);
        $this->assertTrue(ActivityLog::query()->where('action', 'module_update_success')->exists());
        Http::assertSent(fn (Request $request) => $request->url() === self::SERVER.'/api/distribution/v1/report'
            && $request['results'][0]['status'] === 'success' && $request['results'][0]['to_version'] === '1.1.0');
    }

    public function test_a_version_that_failed_is_not_retried_automatically(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0', ['2026_11_01_000002_break.php' => $this->migration('upd_probe_never', fail: true)]);
        $this->fakeServer(offers: [$offer], mode: 'auto', downloads: [$offer['download_url'] => File::get($zip)]);

        $this->artisan('core:updates')->assertFailed();
        $this->assertSame('rolled_back', app(UpdateService::class)->history()[0]['status']);

        $this->artisan('core:updates')->expectsOutputToContain(__('updates.not_retried'))->assertFailed();
        $this->assertCount(1, app(UpdateService::class)->history(), 'not tried a second time');
    }

    public function test_manual_sites_wait_for_the_admin(): void
    {
        $this->installFixture('1.0.0');
        [$offer, $zip] = $this->package('1.1.0');
        $this->fakeServer(offers: [$offer], mode: 'manual', downloads: [$offer['download_url'] => File::get($zip)]);

        $this->artisan('core:updates')->assertSuccessful();
        $this->assertSame('1.0.0', app(ModuleManager::class)->installedVersion('UpdProbe'));

        // The admin clicks Install on Super Admin → Updates.
        $root = \App\Models\User::query()->where('email', 'root@example.com')->firstOrFail();
        $this->actingAs($root, 'staff');
        $this->get(route('superadmin.updates.index'))->assertOk()->assertSee('UpdProbe')->assertSee('1.0.0 → 1.1.0');
        $this->withConfirmedPassword()->post(route('superadmin.updates.install'), ['module' => 'UpdProbe'])->assertSessionHas('success');

        $this->assertSame('1.1.0', app(ModuleManager::class)->installedVersion('UpdProbe'));
    }

    public function test_a_module_taken_away_is_switched_off_and_given_back_switched_on(): void
    {
        NotificationFacade::fake();
        $this->installFixture('1.0.0');
        $root = \App\Models\User::query()->where('email', 'root@example.com')->firstOrFail();
        $this->fakeServer();

        // First check: only remembers the plan.
        $this->plan = ['UpdProbe'];
        app(UpdateService::class)->check();
        $this->assertSame(0, \App\Models\Notification\Notification::query()->count());

        // Taken away.
        $this->plan = [];
        $this->assertTrue(app(UpdateService::class)->check()['status']);

        $this->assertFalse(app(ModuleManager::class)->isEnabled('UpdProbe'));
        $this->assertTrue(app(ModuleManager::class)->isInstalled('UpdProbe'), 'data and install kept');
        $this->assertSame(__('updates.module_taken_away', ['module' => 'UpdProbe']), \App\Models\Notification\Notification::query()->latest('id')->value('title'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/report') && $request['results'][0]['action'] === 'disable' && $request['results'][0]['status'] === 'success');
        $this->assertSame('disable', app(UpdateService::class)->history()[0]['action']);

        // Given back.
        $this->plan = ['UpdProbe'];
        app(UpdateService::class)->check();

        $this->assertTrue(app(ModuleManager::class)->isEnabled('UpdProbe'));
        $this->assertSame(__('updates.module_given_back', ['module' => 'UpdProbe']), \App\Models\Notification\Notification::query()->latest('id')->value('title'));
        NotificationFacade::assertSentTo($root, \App\Notifications\BrandedNotification::class);
    }

    public function test_a_newly_given_module_is_announced_with_what_happens_next(): void
    {
        $this->fakeServer();
        $this->plan = [];
        app(UpdateService::class)->check();

        $this->plan = ['UpdProbeTwo'];
        $this->liveOffers = [$this->offer('UpdProbeTwo', '1.0.0', 'install')];
        app(UpdateService::class)->check();

        $note = \App\Models\Notification\Notification::query()->latest('id')->first();
        $this->assertSame(__('updates.module_given', ['module' => 'UpdProbeTwo']), $note->title);
        $this->assertSame(__('updates.module_given_manual'), $note->body);
    }

    public function test_an_unlinked_server_only_sees_the_link_form(): void
    {
        System::removeProperty('distribution_token');
        $this->actingAs(\App\Models\User::query()->where('email', 'root@example.com')->firstOrFail(), 'staff');

        $this->get(route('superadmin.updates.index'))->assertOk()->assertSee(__('updates.link_title'))->assertSee(__('updates.not_linked_hint'))->assertDontSee(__('updates.check_now'));
        $this->post(route('superadmin.updates.check'))->assertNotFound();
        $this->artisan('core:updates')->expectsOutputToContain(__('updates.not_linked'))->assertSuccessful();
    }

    public function test_customers_get_plain_messages_when_updates_cannot_happen(): void
    {
        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect')]);
        $this->assertSame(__('updates.cannot_reach', ['url' => self::SERVER]), app(UpdateService::class)->check()['message']);

        foreach ([401 => 'access_revoked', 403 => 'access_paused', 503 => 'server_unavailable'] as $status => $message) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['*' => Http::response(['message' => 'x'], $status)]);
            $this->assertSame(__('updates.'.$message), app(UpdateService::class)->check()['message'], "HTTP {$status}");
        }
    }

    public function test_a_link_key_relinks_the_server_after_checking_it(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';
        $key = \App\Support\Distribution\LinkKey::encode(self::SERVER, $uuid, 'ish_'.str_repeat('cd', 24));
        $this->fakeServer();

        $this->assertFalse(app(UpdateService::class)->link('not a key')['status']);

        $result = app(UpdateService::class)->link($key);

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame($uuid, System::getProperty('distribution_site_uuid'));
        $this->assertSame('ish_'.str_repeat('cd', 24), Crypt::decryptString(System::getProperty('distribution_token')));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer ish_'.str_repeat('cd', 24)));
        $this->assertTrue(ActivityLog::query()->where('action', 'update_server_linked')->exists());
    }

    public function test_a_link_key_that_does_not_work_changes_nothing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid token.'], 401)]);
        $key = \App\Support\Distribution\LinkKey::encode(self::SERVER, '11111111-2222-3333-4444-555555555555', 'ish_'.str_repeat('ef', 24));

        $result = app(UpdateService::class)->link($key);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString(__('updates.access_revoked'), $result['message']);
        $this->assertSame('site-1', System::getProperty('distribution_site_uuid'), 'the old link is kept');
        $this->assertSame('ish_secret', Crypt::decryptString(System::getProperty('distribution_token')));
    }

    public function test_the_link_form_and_command(): void
    {
        $this->fakeServer();
        $key = \App\Support\Distribution\LinkKey::encode(self::SERVER, '11111111-2222-3333-4444-555555555555', 'ish_'.str_repeat('ab', 24));
        $this->actingAs(\App\Models\User::query()->where('email', 'root@example.com')->firstOrFail(), 'staff');

        $this->withConfirmedPassword()->post(route('superadmin.updates.link'), ['key' => $key])->assertSessionHas('success');
        $this->artisan('core:link', ['key' => 'ISH1-nope'])->assertFailed();
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function assertRefused(string $message, mixed ...$server): void
    {
        $this->fakeServer(...$server);

        $result = app(UpdateService::class)->check();

        $this->assertFalse($result['status']);
        $this->assertSame(__('updates.'.$message), $result['message']);
        $this->assertSame(__('updates.'.$message), System::getProperty('distribution_last_error'));
        $this->assertSame([], app(UpdateService::class)->offers());
    }

    /**
     * Our update server, faked: signs its answers (unless told to forge).
     */
    private function fakeServer(array $offers = [], string $mode = 'manual', array $downloads = [], bool $forge = false, ?string $nonce = null, ?int $time = null, ?array $assigned = null): void
    {
        Http::fake(function (Request $request) use ($offers, $mode, $downloads, $forge, $nonce, $time, $assigned) {
            if (str_ends_with($request->url(), '/check-in')) {
                $body = json_encode([
                    'site_uuid' => $request['site_uuid'],
                    'nonce' => $nonce ?? $request['nonce'],
                    'server_time' => $time ?? time(),
                    'update_mode' => $mode,
                    'poll_minutes' => 60,
                    'offers' => $offers ?: $this->liveOffers,
                ] + (($assigned ?? $this->plan) !== null ? ['assigned' => $assigned ?? $this->plan] : []));
                $key = $forge ? sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()) : $this->secret;

                return Http::response($body, 200, ['X-Ish-Signature' => base64_encode(sodium_crypto_sign_detached(PackageSignature::responseMessage($body), $key))]);
            }

            if (str_ends_with($request->url(), '/report')) {
                return Http::response(['ok' => true]);
            }

            return isset($downloads[$request->url()]) ? Http::response($downloads[$request->url()]) : Http::response('', 404);
        });
    }

    private function offer(string $module, string $version, string $action, string $sha = null): array
    {
        $sha ??= str_repeat('a', 64);

        return [
            'module' => $module, 'action' => $action, 'from_version' => $action === 'update' ? '1.0.0' : null, 'version' => $version,
            'requires' => [], 'changelog' => null, 'size' => 1, 'sha256' => $sha, 'signature' => $this->sign($module, $version, $sha),
            'download_url' => self::SERVER."/api/distribution/v1/releases/{$module}-{$version}/download",
        ];
    }

    private function sign(string $module, string $version, string $sha): string
    {
        return base64_encode(sodium_crypto_sign_detached(PackageSignature::message($module, $version, $sha), $this->secret));
    }

    /**
     * A signed package of the fixture module at $version.
     *
     * @return array{0: array, 1: string} offer, zip path
     */
    private function package(string $version, array $migrations = [], string $module = 'UpdProbe', string $action = 'update'): array
    {
        $files = [
            "{$module}/module.json" => json_encode(['name' => $module, 'alias' => strtolower($module), 'version' => $version, 'providers' => [], 'files' => []]),
            "{$module}/database/migrations/.gitkeep" => '',
        ];

        foreach ($migrations as $name => $code) {
            $files["{$module}/database/migrations/{$name}"] = $code;
        }

        $zip = $this->zip($files);
        $sha = hash_file('sha256', $zip);

        return [['download_url' => self::SERVER."/api/distribution/v1/releases/{$module}-{$version}/download"] + $this->offer($module, $version, $action, $sha), $zip];
    }

    private function installFixture(string $version): void
    {
        $dir = base_path('Modules/UpdProbe');
        File::ensureDirectoryExists($dir.'/database/migrations');
        File::put($dir.'/module.json', json_encode(['name' => 'UpdProbe', 'alias' => 'updprobe', 'version' => $version, 'providers' => [], 'files' => []]));

        app(ModuleManager::class)->rescan();
        $this->assertTrue(app(ModuleManager::class)->install('UpdProbe')['status']);
    }

    private function migration(string $table, bool $fail = false): string
    {
        $throw = $fail ? "throw new RuntimeException('boom');" : '';

        return <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', fn (Blueprint \$table) => \$table->id());
                {$throw}
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP;
    }

    private function zip(array $files, ?string $symlink = null): string
    {
        $path = $this->tmp.'/'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);

            if ($name === $symlink) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
            }
        }

        $zip->close();

        return $path;
    }
}
