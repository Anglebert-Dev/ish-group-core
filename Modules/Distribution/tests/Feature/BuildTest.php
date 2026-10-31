<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Support\Installer\SiteManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Enums\BuildStatus;
use Modules\Distribution\Jobs\BuildSiteJob;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\SiteToken;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;
use ZipArchive;

/**
 * Self-hosting phase 3 (docs/self-hosting-build-guide.md §6): zips per site.
 */
class BuildTest extends TestCase
{
    protected User $root;

    protected string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->root = $this->makeSuperAdmin(['email' => 'root@example.com']);

        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [], 'app.url' => 'https://updates.example.com']);
        $this->publicKey = $keys['public'];

        Storage::fake('local');
    }

    public function test_start_gives_a_readable_install_code_once(): void
    {
        $started = app(BuildService::class)->start($this->site(['Savings']));

        $this->assertTrue($started['status']);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $started['data']['code']);

        $build = $started['data']['build'];
        $this->assertSame(BuildStatus::Queued, $build->status);
        $this->assertTrue(password_verify(SiteManifest::normalizeCode(strtolower($started['data']['code'])), $build->install_code_hash));
    }

    public function test_a_site_needs_modules_and_we_need_a_key(): void
    {
        $this->assertFalse(app(BuildService::class)->start($this->site([]))['status']);

        config(['distribution.signing_secret' => null]);
        $this->assertFalse(app(BuildService::class)->start($this->site(['Savings']))['status']);
    }

    public function test_the_zip_holds_only_this_sites_modules_and_its_link_back(): void
    {
        $site = $this->site(['Savings']);
        $started = app(BuildService::class)->start($site);
        $result = app(BuildService::class)->run($started['data']['build'], vendor: false);

        $this->assertTrue($result['status'], $result['message']);
        $build = $result['data']->fresh();
        $this->assertSame(BuildStatus::Ready, $build->status);
        $this->assertTrue($build->isDownloadable());

        $file = app(BuildService::class)->absolutePath($build);
        $this->assertSame(hash_file('sha256', $file), $build->sha256);

        $extract = sys_get_temp_dir().'/ish-build-'.uniqid();
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->getExternalAttributesName(\Illuminate\Support\Str::slug($site->name).'/artisan', $system, $attributes);
        $this->assertSame(0755, ($attributes >> 16) & 0777, 'artisan is executable');
        $zip->extractTo($extract);
        $zip->close();

        try {
            $dirs = File::directories($extract);
            $this->assertCount(1, $dirs, 'one top folder');
            $root = $dirs[0];

            $modules = array_map('basename', File::directories($root.'/Modules'));
            $this->assertSame(['Savings'], $modules);
            $this->assertDirectoryDoesNotExist($root.'/Modules/Savings/tests');
            foreach (['tests', 'docs', 'phpunit.xml', 'database/seeders/DemoFactorySeeder.php', '.env', 'README.md'] as $gone) {
                $this->assertFileDoesNotExist($root.'/'.$gone);
            }
            $this->assertFileExists($root.'/artisan');
            $this->assertFileExists($root.'/.htaccess');

            $manifest = json_decode(File::get($root.'/storage/app/distribution/site.json'), true);
            $this->assertSame($site->uuid, $manifest['site_uuid']);
            $this->assertSame('https://updates.example.com', $manifest['server_url']);
            $this->assertSame(['Savings'], $manifest['modules']);
            $this->assertSame($build->install_code_hash, $manifest['install_code_hash']);

            $token = SiteToken::query()->where('token_hash', SiteToken::hash($manifest['token']))->firstOrFail();
            $this->assertSame($build->id, $token->build_id);
            $this->assertSame($token->id, $build->token_id);

            $shipped = require $root.'/config/distribution.php';
            $this->assertSame([$this->publicKey], $shipped['public_keys']);
            $this->assertContains('Billing', $shipped['never_ship']);
            $this->assertSame($build->core_version, (require $root.'/config/core.php')['version']);

            $env = File::get($root.'/.env.example');
            $this->assertStringContainsString('APP_ENV=production', $env);
            $this->assertStringContainsString('DB_DATABASE=:memory:', $env);
            $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $env);
        } finally {
            File::deleteDirectory($extract);
        }

        $this->assertDirectoryDoesNotExist(Storage::disk('local')->path('distribution/work/'.$build->uuid), 'staging cleaned up');
    }

    public function test_a_failed_build_says_why_and_leaves_no_working_token(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128)]);
        $site = $this->site(['Savings']);

        $result = app(BuildService::class)->run(app(BuildService::class)->start($site)['data']['build'], vendor: false);

        $this->assertFalse($result['status']);
        $build = Build::query()->firstOrFail();
        $this->assertSame(BuildStatus::Failed, $build->status);
        $this->assertStringContainsString('not a git repository', $build->error);
        $this->assertSame(0, $site->tokens()->whereNull('revoked_at')->count());
    }

    public function test_build_button_queues_the_job_and_shows_the_code(): void
    {
        Queue::fake();
        $this->actingAs($this->root, 'staff');
        $site = $this->site(['Savings']);

        $this->post(route('superadmin.distribution.sites.builds.store', $site))
            ->assertSessionHas('success')
            ->assertSessionHas('install_code');

        Queue::assertPushed(BuildSiteJob::class);
        $this->get(route('superadmin.distribution.sites.show', $site))->assertOk()->assertSee(__('distribution::distribution.build_status_queued'));
    }

    public function test_download_needs_a_ready_unexpired_build(): void
    {
        $this->actingAs($this->root, 'staff');
        $site = $this->site(['Savings']);
        Storage::disk('local')->put('distribution/builds/site.zip', 'zip');

        $build = Build::query()->create([
            'site_id' => $site->id, 'modules' => ['Savings'], 'status' => BuildStatus::Ready,
            'path' => 'distribution/builds/site.zip', 'filename' => 'site.zip', 'expires_at' => now()->addDay(),
        ]);

        $this->withConfirmedPassword()->get(route('superadmin.distribution.sites.builds.download', [$site, $build]))->assertOk()->assertDownload('site.zip');

        $build->update(['expires_at' => now()->subMinute()]);
        $this->withConfirmedPassword()->get(route('superadmin.distribution.sites.builds.download', [$site, $build]))->assertNotFound();
    }

    private function site(array $modules): Site
    {
        return app(SiteService::class)->create(['name' => 'Kigali Finance '.$this->nextSeq()], $modules)['data'];
    }
}
