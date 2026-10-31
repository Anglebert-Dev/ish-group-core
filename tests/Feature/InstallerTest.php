<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use App\Models\User;
use App\Services\Install\DatabaseProbe;
use App\Services\Install\EnvWriter;
use App\Services\Install\InstallationService;
use App\Support\Installer\Installer;
use App\Support\Installer\SiteManifest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

/**
 * Self-hosting phase 1 (docs/self-hosting-build-guide.md §5.1): web
 * installer, core:setup and the shared InstallationService.
 */
class InstallerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/ish-installer-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        @unlink(storage_path('app/installing.json'));
        $this->restoreInstalledFlag();

        parent::tearDown();
    }

    // ── Redirects ────────────────────────────────────────────────

    public function test_a_fresh_copy_sends_every_page_to_the_installer(): void
    {
        $this->withoutInstalledFlag();

        $this->get('/app/login')->assertRedirect('/install');
        $this->get('/install')->assertRedirect(route('install.code'));
        $this->get('/install/code')->assertRedirect(route('install.requirements'));
        $this->get('/install/requirements')->assertOk()->assertSee(__('install.step_requirements'));
    }

    public function test_installer_is_gone_once_installed(): void
    {
        $this->get('/install')->assertNotFound();
        $this->get('/install/review')->assertNotFound();
    }

    public function test_a_server_set_up_before_the_installer_existed_is_marked_installed(): void
    {
        $this->makeStaff();
        $this->withoutInstalledFlag();

        $this->get('/app/login')->assertOk();
        $this->assertFileExists(Installer::markerPath());
        $this->get('/install')->assertNotFound();
    }

    // ── Web wizard ───────────────────────────────────────────────

    public function test_install_code_is_required_when_the_package_has_one(): void
    {
        $this->withoutInstalledFlag();
        $this->useManifest(['install_code_hash' => password_hash('ABC123XYZ9', PASSWORD_DEFAULT)]);

        $this->get('/install/code')->assertOk();
        $this->get('/install/requirements')->assertRedirect(route('install.code'));

        $this->post('/install/code', ['code' => 'wrong'])->assertSessionHasErrors('code');
        $this->post('/install/code', ['code' => 'abc123xyz9'])->assertRedirect(route('install.requirements'));
    }

    public function test_wizard_collects_every_step_then_runs_the_installer(): void
    {
        $this->withoutInstalledFlag();

        $probe = Mockery::mock(DatabaseProbe::class);
        $probe->shouldReceive('test')->once()->andReturn(['ok' => true, 'has_users' => false, 'message' => 'Connected.']);
        $this->app->instance(DatabaseProbe::class, $probe);

        $this->withSession(['install.done' => ['code', 'requirements']]);

        $this->post('/install/database', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'database' => 'finance', 'username' => 'finance', 'password' => 'p$ss',
        ])->assertRedirect(route('install.app'));

        $this->post('/install/app', ['name' => 'Finance', 'url' => 'http://finance.test', 'timezone' => 'Africa/Kigali', 'queue_via_scheduler' => '1'])
            ->assertRedirect(route('install.mail'));
        $this->post('/install/mail', ['action' => 'skip'])->assertRedirect(route('install.admin'));
        $this->post('/install/admin', [
            'business' => 'Jane', 'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'password' => self::TEST_PASSWORD, 'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('install.review'));

        $this->get('/install/review')->assertOk()->assertSee('finance@127.0.0.1:3306/finance');

        $installer = Mockery::mock(InstallationService::class);
        $installer->shouldReceive('run')->once()
            ->withArgs(fn (array $data) => $data['database']['password'] === 'p$ss'
                && $data['app']['queue_via_scheduler'] === true
                && $data['mail'] === null
                && $data['admin']['email'] === 'jane@example.com')
            ->andReturn(['status' => true, 'data' => ['business_id' => 1, 'modules' => []], 'message' => 'Installed.']);
        $this->app->instance(InstallationService::class, $installer);

        $this->post('/install/run')->assertOk()
            ->assertSee('schedule:run')
            ->assertSee('http://finance.test/app/login')
            ->assertSessionMissing('install.data');
    }

    public function test_a_step_cannot_be_skipped(): void
    {
        $this->withoutInstalledFlag();
        $this->withSession(['install.done' => ['code', 'requirements']]);

        $this->get('/install/admin')->assertRedirect(route('install.database'));
        $this->post('/install/run')->assertRedirect(route('install.database'));
    }

    // ── InstallationService ──────────────────────────────────────

    public function test_installation_creates_platform_modules_link_and_marker(): void
    {
        $this->useManifest(['site_uuid' => 'site-1', 'server_url' => 'https://updates.example.com', 'token' => 'secret-token', 'modules' => ['Quotations']]);
        Http::fake(['updates.example.com/*' => Http::response('down', 503)]);

        $result = $this->installer()->run($this->data());

        $this->assertTrue($result['status'], $result['message']);
        $this->assertTrue(User::query()->where('email', 'jane@example.com')->firstOrFail()->isSuperAdmin());

        foreach (['Inventory', 'SalesOrders', 'Quotations'] as $module) {
            $this->assertNotNull(System::getProperty(strtolower($module).'_version'), "{$module} installed");
        }

        $this->assertSame('site-1', System::getProperty('distribution_site_uuid'));
        $this->assertSame('secret-token', Crypt::decryptString(System::getProperty('distribution_token')));
        $this->assertArrayNotHasKey('token', json_decode(file_get_contents($this->tmp.'/site.json'), true), 'token leaves site.json');

        $this->assertFileExists(Installer::markerPath());
        $this->assertTrue(ActivityLog::query()->where('action', 'platform_installed')->exists());
        $this->assertFileDoesNotExist(storage_path('app/installing.json'));

        // First check-in tried; our server being down doesn't fail the install.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/distribution/v1/check-in') && $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_installation_refuses_a_database_with_users(): void
    {
        $this->makeStaff();

        $result = $this->installer()->run($this->data());

        $this->assertFalse($result['status']);
        $this->assertSame(__('install.db_has_users'), $result['message']);
    }

    public function test_a_failed_step_can_be_run_again_and_carries_on(): void
    {
        $failed = $this->installer()->run($this->data(['modules' => ['NoSuchModule']]));

        $this->assertFalse($failed['status']);
        $this->assertStringContainsString(__('install.step_modules'), $failed['message']);
        $this->assertSame(['env', 'migrate', 'platform'], json_decode(file_get_contents(storage_path('app/installing.json')), true));

        $this->assertTrue($this->installer()->resuming());

        // Users exist now, but the saved progress lets it carry on.
        $retry = $this->installer()->run($this->data(['modules' => ['Frontend']]));

        $this->assertTrue($retry['status'], $retry['message']);
        $this->assertSame(1, User::query()->count());
        $this->assertNotNull(System::getProperty('frontend_version'));
    }

    public function test_modules_install_requirements_first(): void
    {
        $this->assertSame(['Inventory', 'SalesOrders', 'Quotations'], $this->installer()->installOrder(['Quotations']));
        $this->assertSame(['Inventory', 'Production', 'GarmentPack'], $this->installer()->installOrder(['GarmentPack']));
    }

    public function test_env_values_follow_the_answers(): void
    {
        $values = $this->installer()->envValues([
            'database' => ['driver' => 'pgsql', 'host' => 'db', 'port' => 5432, 'database' => 'f', 'username' => 'u', 'password' => 'p'],
            'app' => ['name' => 'Finance', 'url' => 'https://finance.example.com/', 'timezone' => 'UTC', 'queue_via_scheduler' => true],
            'mail' => null,
        ]);

        $this->assertSame('https://finance.example.com', $values['APP_URL']);
        $this->assertSame('pgsql', $values['DB_CONNECTION']);
        $this->assertTrue($values['SESSION_SECURE_COOKIE']);
        $this->assertTrue($values['QUEUE_VIA_SCHEDULER']);
        $this->assertSame('log', $values['MAIL_MAILER']);
        $this->assertFalse($values['APP_DEBUG']);
    }

    // ── core:setup ───────────────────────────────────────────────

    public function test_setup_command_installs_without_questions(): void
    {
        $this->withoutInstalledFlag();
        $this->useManifest([]);

        $this->artisan('core:setup', [
            '--skip-env' => true,
            '--no-interaction' => true,
            '--business' => 'Jane',
            '--admin-name' => 'Jane Doe',
            '--admin-email' => 'jane@example.com',
            '--admin-password' => self::TEST_PASSWORD,
            '--modules' => 'Savings',
        ])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'jane@example.com')->exists());
        $this->assertNotNull(System::getProperty('savings_version'));
        $this->assertFileExists(Installer::markerPath());
    }

    public function test_setup_command_rejects_invalid_input(): void
    {
        $this->withoutInstalledFlag();

        $this->artisan('core:setup', [
            '--skip-env' => true,
            '--no-interaction' => true,
            '--business' => 'Jane',
            '--admin-name' => 'Jane Doe',
            '--admin-email' => 'not-an-email',
            '--admin-password' => 'short',
        ])->assertFailed();

        $this->assertFalse(User::query()->exists());
    }

    // ── EnvWriter ────────────────────────────────────────────────

    public function test_env_writer_keeps_other_lines_and_round_trips_values(): void
    {
        $path = $this->tmp.'/.env';
        file_put_contents($path, "APP_KEY=base64:keep-me\nDB_PASSWORD=old\n");
        $writer = new EnvWriter($path);

        $tricky = 'p$ss "word" \\ #not-a-comment ${HOME}';
        $writer->set(['DB_PASSWORD' => $tricky, 'APP_NAME' => 'My Finance', 'APP_DEBUG' => false]);

        $this->assertSame('base64:keep-me', $writer->get('APP_KEY'));
        $this->assertSame($tricky, $writer->get('DB_PASSWORD'));
        $this->assertSame('My Finance', $writer->get('APP_NAME'));
        $this->assertSame('false', $writer->get('APP_DEBUG'));
        $this->assertSame(1, substr_count(file_get_contents($path), 'DB_PASSWORD='));
    }

    public function test_env_writer_refuses_line_breaks(): void
    {
        $writer = new EnvWriter($this->tmp.'/.env');

        $this->expectException(InvalidArgumentException::class);
        $writer->set(['APP_NAME' => "x\nAPP_DEBUG=true"]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function installer(): InstallationService
    {
        $this->withoutInstalledFlag();

        return app(InstallationService::class);
    }

    private function data(array $overrides = []): array
    {
        return $overrides + [
            'skip_env' => true, // the test database is already configured
            'app' => ['timezone' => 'Africa/Kigali'],
            'admin' => ['business' => 'Jane', 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => self::TEST_PASSWORD],
        ];
    }

    private function useManifest(array $data): void
    {
        file_put_contents($this->tmp.'/site.json', json_encode($data));
        $this->app->bind(SiteManifest::class, fn () => new SiteManifest($this->tmp.'/site.json'));
    }

    private array $savedFlag = [];

    private function withoutInstalledFlag(): void
    {
        if ($this->savedFlag !== []) {
            return;
        }

        $this->savedFlag = [$_ENV['APP_INSTALLED'] ?? null, $_SERVER['APP_INSTALLED'] ?? null, getenv('APP_INSTALLED')];
        unset($_ENV['APP_INSTALLED'], $_SERVER['APP_INSTALLED']);
        putenv('APP_INSTALLED');
    }

    private function restoreInstalledFlag(): void
    {
        if ($this->savedFlag === []) {
            return;
        }

        [$env, $server, $process] = $this->savedFlag;
        $env !== null && $_ENV['APP_INSTALLED'] = $env;
        $server !== null && $_SERVER['APP_INSTALLED'] = $server;
        $process !== false && putenv('APP_INSTALLED='.$process);
        $this->savedFlag = [];
    }
}
