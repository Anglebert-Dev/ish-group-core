<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Services\Updates\CoreUpdater;
use App\Services\Updates\UpdateClient;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Core updates on a customer's server (docs/self-hosting-build-guide.md §5.3).
 * The swap is tested on a pretend app folder with a pretend artisan, so
 * nothing real is replaced.
 */
class CoreUpdateTest extends TestCase
{
    private string $tmp;

    private string $base;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/ish-core-'.uniqid();
        $this->base = $this->tmp.'/app';
        File::ensureDirectoryExists($this->base);

        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        config(['distribution.public_keys' => [base64_encode(sodium_crypto_sign_publickey($pair))], 'distribution.backup_before_update' => false]);

        System::addProperty('distribution_server_url', 'https://updates.example.com');
        System::addProperty('distribution_site_uuid', 'site-1');
        System::addProperty('distribution_token', Crypt::encryptString('ish_secret'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        foreach (File::glob(database_path('migrations/2099_*')) as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    // ── apply-core.php ───────────────────────────────────────────

    public function test_the_script_swaps_the_code_and_keeps_data_and_uploads(): void
    {
        $this->oldApp();
        $source = $this->newCore('1.1.0');
        $publicFolder = fileinode($this->base.'/public');

        [$code, $result] = $this->apply($source);

        $this->assertSame(0, $code);
        $this->assertSame($publicFolder, fileinode($this->base.'/public'), 'public/ itself stays (web servers serve from it)');
        $this->assertSame('success', $result['status']);
        $this->assertSame('new', trim(File::get($this->base.'/app/marker.txt')));
        $this->assertSame('new', trim(File::get($this->base.'/public/index.php')));
        $this->assertFileDoesNotExist($this->base.'/app/old-only.txt', 'files gone from the new core are gone');
        $this->assertSame('photo', File::get($this->base.'/public/storage/upload.jpg'), 'uploads untouched');
        $this->assertFileDoesNotExist($this->tmp.'/backup/public/storage', 'uploads were never moved');
        $this->assertSame('APP_KEY=keep', File::get($this->base.'/.env'));
        $this->assertSame('data', File::get($this->base.'/storage/app/keep.txt'));
        $this->assertFileExists($this->base.'/Modules/Savings/module.json');
        $this->assertSame('old', trim(File::get($this->tmp.'/backup/app/marker.txt')), 'old code kept as backup');
        $this->assertSame(['old down', 'new core:after-update', 'new up'], $this->artisanCalls());
    }

    public function test_the_script_puts_everything_back_when_the_new_code_fails(): void
    {
        $this->oldApp();
        $source = $this->newCore('1.1.0');
        File::put($this->tmp.'/fail.flag', 'migration exploded');

        [$code, $result] = $this->apply($source);

        $this->assertSame(1, $code);
        $this->assertSame('rolled_back', $result['status']);
        $this->assertStringContainsString('migration exploded', $result['error']);
        $this->assertSame('old', trim(File::get($this->base.'/app/marker.txt')));
        $this->assertFileExists($this->base.'/app/old-only.txt');
        $this->assertSame('old', trim(File::get($this->base.'/public/index.php')));
        $this->assertSame('photo', File::get($this->base.'/public/storage/upload.jpg'), 'uploads still there');
        $this->assertFileDoesNotExist($this->tmp.'/backup/public/storage', 'uploads were never moved');
        $this->assertSame('APP_KEY=keep', File::get($this->base.'/.env'));
        $this->assertSame(['old down', 'new core:after-update', 'old up'], $this->artisanCalls(), 'back out of maintenance on the old code');
    }

    // ── CoreUpdater ──────────────────────────────────────────────

    public function test_a_core_package_is_checked_before_anything_changes(): void
    {
        $this->oldApp();
        $zip = $this->zipCore('1.1.0');
        $offer = $this->offer('1.1.0', $zip);
        $updater = new CoreUpdater(app(UpdateClient::class), $this->base);

        $tooOld = $updater->install(['min_php' => '99.0'] + $offer, $zip, download: false);
        $forged = $updater->install(['signature' => base64_encode(str_repeat('x', 64))] + $offer, $zip, download: false);
        $wrongVersion = $updater->install(['version' => '1.2.0', 'signature' => $this->sign('1.2.0', $offer['sha256'])] + $offer, $zip, download: false);

        $this->assertStringContainsString('99.0', $tooOld['error']);
        $this->assertSame(__('updates.bad_package_signature'), $forged['error']);
        $this->assertSame(__('updates.manifest_mismatch'), $wrongVersion['error']);
        $this->assertSame('old', trim(File::get($this->base.'/app/marker.txt')), 'nothing changed');
        $this->assertSame([], $this->artisanCalls(), 'not even maintenance mode');
    }

    public function test_a_verified_core_package_is_installed_and_recorded(): void
    {
        $this->oldApp();
        $zip = $this->zipCore('1.1.0');
        $updater = new CoreUpdater(app(UpdateClient::class), $this->base);

        $outcome = $updater->install($this->offer('1.1.0', $zip), $zip, download: false);
        $updater->finish($outcome);

        $this->assertSame('success', $outcome['status'], (string) $outcome['error']);
        $this->assertSame('core', $outcome['action']);
        $this->assertSame('new', trim(File::get($this->base.'/app/marker.txt')));
        $this->assertContains('new core:finish-core-update', $this->artisanCalls(), 'recorded by the new code');
        $this->assertCount(1, File::directories($this->base.'/storage/app/updates/core-backups'));
    }

    // ── core:after-update ────────────────────────────────────────

    public function test_after_update_undoes_its_migrations_when_one_fails(): void
    {
        File::put(database_path('migrations/2099_01_01_000001_core_ok.php'), $this->migration('core_e2e_ok'));
        File::put(database_path('migrations/2099_01_01_000002_core_bad.php'), $this->migration('core_e2e_half', fail: true));

        $this->artisan('core:after-update')->assertFailed();

        $this->assertFalse(Schema::hasTable('core_e2e_ok'));
        $this->assertFalse(Schema::hasTable('core_e2e_half'));
        $this->assertFalse(DB::table('migrations')->where('migration', 'like', '2099_%')->exists());
    }

    public function test_after_update_runs_new_migrations(): void
    {
        File::put(database_path('migrations/2099_01_01_000001_core_ok.php'), $this->migration('core_e2e_ok'));

        $this->artisan('core:after-update')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('core_e2e_ok'));
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** The running app: old code, data and uploads that must survive. */
    private function oldApp(): void
    {
        foreach ([
            'app/marker.txt' => 'old', 'app/old-only.txt' => 'gone after update', 'public/index.php' => 'old',
            'public/storage/upload.jpg' => 'photo', 'config/core.php' => "<?php return ['version' => '1.0.0'];",
            'vendor/autoload.php' => '<?php', 'composer.json' => '{}', '.env' => 'APP_KEY=keep',
            'storage/app/keep.txt' => 'data', 'Modules/Savings/module.json' => '{}', 'bootstrap/cache/packages.php' => '<?php',
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname($this->base.'/'.$path));
            File::put($this->base.'/'.$path, $contents);
        }

        File::put($this->base.'/artisan', $this->fakeArtisan('old'));
        File::ensureDirectoryExists($this->base.'/app/Support/Updates');
        File::copy(base_path('app/Support/Updates/apply-core.php'), $this->base.'/app/Support/Updates/apply-core.php');
    }

    private function newCore(string $version): string
    {
        $source = $this->tmp.'/new/core';

        foreach ([
            'app/marker.txt' => 'new', 'public/index.php' => 'new', 'config/core.php' => "<?php return ['version' => '{$version}'];",
            'vendor/autoload.php' => '<?php', 'composer.json' => '{}', 'artisan' => $this->fakeArtisan('new'),
            'app/Support/Updates/apply-core.php' => File::get(base_path('app/Support/Updates/apply-core.php')),
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname($source.'/'.$path));
            File::put($source.'/'.$path, $contents);
        }

        return $source;
    }

    private function zipCore(string $version): string
    {
        $source = $this->newCore($version);
        $zip = new ZipArchive;
        $path = $this->tmp.'/core.zip';
        $zip->open($path, ZipArchive::CREATE);

        foreach (File::allFiles($source, true) as $file) {
            $zip->addFile($file->getPathname(), 'core/'.$file->getRelativePathname());
        }

        $zip->close();
        File::deleteDirectory($this->tmp.'/new');

        return $path;
    }

    private function offer(string $version, string $zip): array
    {
        $sha = hash_file('sha256', $zip);

        return ['module' => 'Core', 'action' => 'core', 'version' => $version, 'from_version' => '1.0.0', 'sha256' => $sha, 'signature' => $this->sign($version, $sha), 'min_php' => '8.3', 'download_url' => 'https://updates.example.com/x'];
    }

    private function sign(string $version, string $sha): string
    {
        return base64_encode(sodium_crypto_sign_detached(PackageSignature::message('Core', $version, $sha), $this->secret));
    }

    /**
     * Logs "<which> <command>"; core:after-update fails when fail.flag exists.
     */
    private function fakeArtisan(string $which): string
    {
        $log = var_export($this->tmp.'/calls.log', true);
        $flag = var_export($this->tmp.'/fail.flag', true);

        return <<<PHP
        <?php
        file_put_contents({$log}, '{$which} '.(\$argv[1] ?? '').PHP_EOL, FILE_APPEND);
        if ((\$argv[1] ?? '') === 'core:after-update' && is_file({$flag})) { fwrite(STDERR, file_get_contents({$flag})); exit(1); }
        exit(0);
        PHP;
    }

    /**
     * @return array{0: int, 1: array}
     */
    private function apply(string $source): array
    {
        $script = $this->tmp.'/apply-core.php';
        File::copy(base_path('app/Support/Updates/apply-core.php'), $script);
        exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, $script, $this->base, $source, $this->tmp.'/backup', $this->tmp.'/result.json', PHP_BINARY])), $output, $code);

        return [$code, json_decode((string) File::get($this->tmp.'/result.json'), true)];
    }

    private function artisanCalls(): array
    {
        return is_file($this->tmp.'/calls.log') ? array_values(array_filter(array_map('trim', file($this->tmp.'/calls.log')))) : [];
    }

    private function migration(string $table, bool $fail = false): string
    {
        $throw = $fail ? "throw new RuntimeException('boom');" : '';

        return <<<PHP
        <?php
        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;
        return new class extends Migration {
            public function up(): void { Schema::create('{$table}', fn (Blueprint \$t) => \$t->id()); {$throw} }
            public function down(): void { Schema::dropIfExists('{$table}'); }
        };
        PHP;
    }
}
