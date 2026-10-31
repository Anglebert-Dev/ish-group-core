<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Models\User;
use App\Support\Installer\Installer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Self-hosting phase 0 (docs/self-hosting-build-guide.md): the "installed"
 * marker, the extracted first-platform setup and the module autoloader.
 */
class InstallerGroundworkTest extends TestCase
{
    public function test_app_installed_env_counts_as_installed(): void
    {
        $this->assertFalse(is_file(Installer::markerPath()));
        $this->assertTrue(Installer::isInstalled(), 'phpunit.xml sets APP_INSTALLED=true');
    }

    public function test_marker_decides_when_the_env_flag_is_absent(): void
    {
        $this->withoutInstalledFlag(function () {
            $this->assertFalse(Installer::isInstalled());

            Installer::markInstalled(version: '1.0.0');
            Installer::markInstalled(); // a second call must not fail on the read-only file

            $this->assertTrue(Installer::isInstalled());
            $this->assertSame('1.0.0', json_decode(file_get_contents(Installer::markerPath()), true)['version']);
        });
    }

    public function test_core_install_creates_the_platform_and_writes_the_marker(): void
    {
        $this->artisan('core:install', [
            '--business' => 'Installed Co',
            '--name' => 'Root',
            '--email' => 'root@example.com',
            '--password' => self::TEST_PASSWORD,
        ])->assertSuccessful();

        $root = User::query()->where('email', 'root@example.com')->firstOrFail();
        $this->assertSame((string) $root->business_id, (string) System::getProperty('primary_business_id'));
        $this->assertFileExists(Installer::markerPath());

        // Running it again on an installed server is harmless.
        $this->artisan('core:install', ['--force' => true])->assertSuccessful();
    }

    public function test_mark_installed_command(): void
    {
        $this->artisan('core:mark-installed')->assertSuccessful();
        $this->assertFileExists(Installer::markerPath());

        $this->artisan('core:mark-installed')->expectsOutputToContain('Already')->assertSuccessful();
    }

    public function test_a_module_unknown_to_composer_still_autoloads(): void
    {
        $dir = base_path('Modules/ZzAutoloadProbe');
        File::ensureDirectoryExists($dir.'/app/Support');
        File::put($dir.'/app/Support/Probe.php', "<?php\n\nnamespace Modules\\ZzAutoloadProbe\\Support;\n\nclass Probe\n{\n    public const OK = 'loaded';\n}\n");

        try {
            $this->assertSame('loaded', \Modules\ZzAutoloadProbe\Support\Probe::OK);
            $this->assertFalse(class_exists('Modules\\ZzAutoloadProbe\\..\\Nope'));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function withoutInstalledFlag(callable $test): void
    {
        $saved = [$_ENV['APP_INSTALLED'] ?? null, $_SERVER['APP_INSTALLED'] ?? null, getenv('APP_INSTALLED')];
        unset($_ENV['APP_INSTALLED'], $_SERVER['APP_INSTALLED']);
        putenv('APP_INSTALLED');

        try {
            $test();
        } finally {
            [$env, $server, $process] = $saved;
            if ($env !== null) {
                $_ENV['APP_INSTALLED'] = $env;
            }
            if ($server !== null) {
                $_SERVER['APP_INSTALLED'] = $server;
            }
            if ($process !== false) {
                putenv('APP_INSTALLED='.$process);
            }
        }
    }
}
