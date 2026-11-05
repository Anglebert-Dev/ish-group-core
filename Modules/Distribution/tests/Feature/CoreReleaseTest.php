<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\Notification\Notification;
use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\ReleaseService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Modules\Distribution\Tests\Feature\Concerns\PaidSite;
use Tests\TestCase;
use ZipArchive;

/**
 * Core updates, our side (docs/self-hosting-build-guide.md §5.3).
 */
class CoreReleaseTest extends TestCase
{
    use PaidSite;

    protected string $publicKey;

    protected Site $site;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->makeSuperAdmin(['email' => 'root@example.com']);
        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [$keys['public']]]);
        $this->publicKey = $keys['public'];
        Storage::fake('local');

        $this->site = $this->paid(app(SiteService::class)->create(['name' => 'Acme'], ['Savings'])['data']);
        $this->token = app(SiteService::class)->issueToken($this->site);
    }

    public function test_the_core_package_has_no_modules_and_is_signed(): void
    {
        $result = app(ReleaseService::class)->publishCore('Faster reports', vendor: false);

        $this->assertTrue($result['status'], $result['message']);
        $release = $result['data'];
        $this->assertSame(Release::CORE, $release->module);
        $this->assertTrue(PackageSignature::verify(PackageSignature::message('Core', $release->version, $release->sha256), $release->signature, [$this->publicKey]));
        $this->assertSame('8.3', $release->min_php);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($release->path));
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $core = $zip->getFromName('core/config/core.php');
        $zip->close();

        $this->assertTrue($names->every(fn ($name) => str_starts_with($name, 'core/')));
        $this->assertFalse($names->contains(fn ($name) => str_starts_with($name, 'core/Modules/') || str_starts_with($name, 'core/storage/')), 'no modules, no storage');
        $this->assertContains('core/app/Support/Updates/apply-core.php', $names);
        $this->assertStringContainsString("'version' => '".$release->version."'", $core);
        $this->assertStringContainsString("'business_types' => [", $core);
    }

    public function test_the_same_core_version_cannot_be_published_twice_and_core_is_reserved(): void
    {
        app(ReleaseService::class)->publishCore(vendor: false);

        $this->assertFalse(app(ReleaseService::class)->publishCore(vendor: false)['status']);
        $this->assertSame(1, Release::query()->where('module', Release::CORE)->count());
    }

    public function test_a_newer_core_is_offered_first_and_waits_for_test_sites_when_asked(): void
    {
        $this->coreRelease('1.1.0', testOnly: true);
        $this->moduleRelease('Savings', '2.0.0', minCore: '1.1.0');

        $this->assertSame([], $this->offered('1.0.0+abc'), 'test-only core and the module needing it wait');

        Release::query()->where('module', Release::CORE)->update(['test_only' => false]);

        $this->assertSame(['Core'], $this->offered('1.0.0+abc'), 'core first; the module waits for it');
        $this->assertSame(['Savings'], $this->offered('1.1.0+def'), 'on the new core, the module comes');
        $this->assertSame(['Core'], $this->offered('f43c183'), 'an old zip (git commit as its version) counts as 0.0.0');
    }

    public function test_a_core_report_updates_the_site_and_tells_us(): void
    {
        $this->withToken($this->token)->postJson(route('distribution.api.report'), [
            'site_uuid' => $this->site->uuid,
            'results' => [['module' => 'Core', 'action' => 'core', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'status' => 'success']],
        ])->assertOk();

        $this->assertSame('1.1.0', $this->site->fresh()->core_version);
        $this->assertSame('Acme updated its core to 1.1.0', Notification::query()->latest('id')->value('title'));
    }

    public function test_the_releases_page_has_publish_core(): void
    {
        $this->actingAs(User::query()->where('email', 'root@example.com')->firstOrFail(), 'staff');

        $this->get(route('superadmin.distribution.releases.index'))->assertOk()->assertSee(__('distribution::distribution.publish_core'))->assertSee(config('core.version'));
    }

    private function offered(string $core): array
    {
        return array_column($this->withToken($this->token)->postJson(route('distribution.api.check-in'), [
            'site_uuid' => $this->site->uuid, 'nonce' => bin2hex(random_bytes(16)), 'core_version' => $core,
            'modules' => [['name' => 'Savings', 'installed_version' => '1.0.0']],
        ])->assertOk()->json('offers'), 'module');
    }

    private function coreRelease(string $version, bool $testOnly = false): Release
    {
        return Release::query()->create(['module' => Release::CORE, 'version' => $version, 'sha256' => str_repeat('a', 64), 'signature' => 's', 'size' => 1, 'path' => 'x', 'published_at' => now(), 'test_only' => $testOnly]);
    }

    private function moduleRelease(string $module, string $version, ?string $minCore = null): Release
    {
        return Release::query()->create(['module' => $module, 'version' => $version, 'sha256' => str_repeat('a', 64), 'signature' => 's', 'size' => 1, 'path' => 'x', 'published_at' => now(), 'min_core_version' => $minCore]);
    }
}
