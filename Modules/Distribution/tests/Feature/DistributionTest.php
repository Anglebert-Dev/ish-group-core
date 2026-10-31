<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Support\Distribution\PackageSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\SiteToken;
use Modules\Distribution\Services\ReleaseService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;
use ZipArchive;

/**
 * Self-hosting phase 2 (docs/self-hosting-build-guide.md §6): sites, module
 * assignment, tokens, signed releases.
 */
class DistributionTest extends TestCase
{
    protected User $root;

    protected string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->root = $this->makeSuperAdmin(['email' => 'root@example.com']);

        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => [$keys['public']]]);
        $this->publicKey = $keys['public'];

        Storage::fake('local');
    }

    // ── Access ───────────────────────────────────────────────────

    public function test_other_users_cannot_open_it(): void
    {
        $this->actingAsStaff();

        $this->get(route('superadmin.distribution.sites.index'))->assertForbidden();
        $this->get(route('superadmin.distribution.releases.index'))->assertForbidden();
    }

    public function test_superadmin_screens_open(): void
    {
        $this->actingAs($this->root, 'staff');
        $site = $this->site(['Savings']);

        $this->get(route('superadmin.distribution.sites.index'))->assertOk()->assertSee($site->name);
        $this->get(route('superadmin.distribution.sites.create'))->assertOk()->assertSee('Savings')->assertDontSee('value="Billing"', false);
        $this->get(route('superadmin.distribution.sites.show', $site))->assertOk()->assertSee('Savings');
        $this->get(route('superadmin.distribution.sites.edit', $site))->assertOk();
        $this->get(route('superadmin.distribution.releases.index'))->assertOk()->assertSee($this->publicKey);
    }

    // ── Sites and modules ────────────────────────────────────────

    public function test_creating_a_site_gives_its_modules_and_what_they_need(): void
    {
        $this->actingAs($this->root, 'staff');

        $this->post(route('superadmin.distribution.sites.store'), [
            'name' => 'Kigali Finance', 'customer_name' => 'Jane', 'update_mode' => 'manual', 'status' => 'active', 'modules' => ['Quotations'],
        ])->assertRedirect();

        $site = Site::query()->where('name', 'Kigali Finance')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Inventory', 'SalesOrders', 'Quotations'], $site->moduleNames());
        $this->assertSame('manual', $site->update_mode->value);
    }

    public function test_billing_and_distribution_are_never_given(): void
    {
        $site = $this->site([]);

        foreach (['Billing', 'Distribution', 'billing'] as $module) {
            $result = app(SiteService::class)->assign($site, [$module]);
            $this->assertFalse($result['status'], "{$module} refused");
        }

        $this->assertSame([], $site->moduleNames());
        $this->assertNotContains('Billing', app(SiteService::class)->assignableModules());
        $this->assertNotContains('Distribution', app(SiteService::class)->assignableModules());
    }

    public function test_a_module_another_one_needs_cannot_be_taken_away(): void
    {
        $this->actingAs($this->root, 'staff');
        $site = $this->site(['SalesOrders']);

        $this->withConfirmedPassword()->delete(route('superadmin.distribution.sites.modules.unassign', [$site, 'Inventory']))
            ->assertSessionHas('error');
        $this->assertContains('Inventory', $site->moduleNames());

        $this->withConfirmedPassword()->delete(route('superadmin.distribution.sites.modules.unassign', [$site, 'SalesOrders']))
            ->assertSessionHas('success');
        $this->assertSame(['Inventory'], $site->moduleNames());
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_module_unassigned')->exists());
    }

    public function test_giving_more_modules_later(): void
    {
        $this->actingAs($this->root, 'staff');
        $site = $this->site(['Savings']);

        $this->post(route('superadmin.distribution.sites.modules.assign', $site), ['modules' => ['Delivery']])->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(['Savings', 'Inventory', 'SalesOrders', 'Delivery'], $site->moduleNames());
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_modules_assigned')->exists());
    }

    // ── Tokens ───────────────────────────────────────────────────

    public function test_only_the_token_hash_is_stored_and_it_can_be_revoked(): void
    {
        $this->actingAs($this->root, 'staff');
        $site = $this->site([]);

        $token = app(SiteService::class)->issueToken($site);

        $this->assertStringStartsWith('ish_', $token);
        $this->assertFalse(DB::table('distribution_site_tokens')->where('token_hash', $token)->exists());
        $row = SiteToken::query()->where('token_hash', SiteToken::hash($token))->firstOrFail();
        $this->assertSame(substr($token, -4), $row->last4);

        $this->withConfirmedPassword()->post(route('superadmin.distribution.sites.tokens.revoke', [$site, $row]))->assertSessionHas('success');
        $this->assertTrue($row->fresh()->isRevoked());
    }

    // ── Releases ─────────────────────────────────────────────────

    public function test_publishing_signs_a_zip_of_the_module(): void
    {
        $result = app(ReleaseService::class)->publish('Savings', 'First release');

        $this->assertTrue($result['status'], $result['message']);
        $release = $result['data'];
        $file = app(ReleaseService::class)->absolutePath($release);

        $this->assertFileExists($file);
        $this->assertSame(hash_file('sha256', $file), $release->sha256);
        $this->assertTrue(PackageSignature::verify(
            PackageSignature::message('Savings', $release->version, $release->sha256), $release->signature, [$this->publicKey],
        ));
        $this->assertFalse(PackageSignature::verify(
            PackageSignature::message('Savings', '9.9.9', $release->sha256), $release->signature, [$this->publicKey],
        ), 'the signature covers the version');

        $zip = new ZipArchive;
        $zip->open($file);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertContains('Savings/module.json', $names);
        $this->assertTrue($names->every(fn ($name) => str_starts_with($name, 'Savings/')), 'one top folder');
        $this->assertFalse($names->contains(fn ($name) => str_starts_with($name, 'Savings/tests')), 'no tests');
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_release_published')->exists());
    }

    public function test_the_same_version_cannot_be_published_twice(): void
    {
        app(ReleaseService::class)->publish('Savings');

        $again = app(ReleaseService::class)->publish('Savings');

        $this->assertFalse($again['status']);
        $this->assertSame(1, Release::query()->where('module', 'Savings')->count());
    }

    public function test_publishing_needs_a_signing_key_and_a_shippable_module(): void
    {
        $this->assertFalse(app(ReleaseService::class)->publish('Billing')['status']);

        config(['distribution.signing_secret' => null]);
        $result = app(ReleaseService::class)->publish('Savings');

        $this->assertFalse($result['status']);
        $this->assertSame(__('distribution::distribution.keys_missing'), $result['message']);
    }

    public function test_publish_and_withdraw_from_the_screen(): void
    {
        $this->actingAs($this->root, 'staff');

        $this->post(route('superadmin.distribution.releases.store'), ['module' => 'Savings', 'changelog' => 'Budget fixes'])->assertSessionHas('success');
        $release = Release::query()->where('module', 'Savings')->firstOrFail();
        $this->assertArrayHasKey('Savings', Release::latestFor(['Savings']));

        $this->withConfirmedPassword()->post(route('superadmin.distribution.releases.yank', $release))->assertSessionHas('success');

        $this->assertNotNull($release->fresh()->yanked_at);
        $this->assertSame([], Release::latestFor(['Savings']), 'a withdrawn release is not offered');
    }

    public function test_latest_release_is_by_version_not_date(): void
    {
        foreach (['1.10.0', '1.9.0'] as $version) {
            Release::query()->create(['module' => 'Savings', 'version' => $version, 'sha256' => str_repeat('a', 64), 'signature' => 'x', 'size' => 1, 'path' => 'x', 'published_at' => now()]);
        }

        $this->assertSame('1.10.0', Release::latestFor(['Savings'])['Savings']->version);
    }

    public function test_the_signing_key_can_be_created_from_the_web(): void
    {
        $env = sys_get_temp_dir().'/ish-env-'.uniqid();
        file_put_contents($env, "APP_NAME=Test\n");
        $this->app->bind(\App\Services\Install\EnvWriter::class, fn () => new \App\Services\Install\EnvWriter($env));
        config(['distribution.signing_secret' => null, 'distribution.public_keys' => []]);
        $this->actingAs($this->root, 'staff');

        $this->get(route('superadmin.distribution.releases.index'))->assertOk()->assertSee(__('distribution::distribution.create_key'));

        try {
            $this->withConfirmedPassword()->post(route('superadmin.distribution.releases.key'))
                ->assertSessionHas('success')
                ->assertSessionHas('signing_secret');

            $this->assertTrue(app(SigningKeys::class)->configured());
            $written = file_get_contents($env);
            $this->assertStringContainsString('DISTRIBUTION_SIGNING_SECRET='.config('distribution.signing_secret'), $written);
            $this->assertStringContainsString('DISTRIBUTION_PUBLIC_KEYS='.app(SigningKeys::class)->publicKey(), $written);
            $this->assertTrue(ActivityLog::query()->where('action', 'distribution_keys_generated')->exists());

            // A second press never replaces it.
            $secret = config('distribution.signing_secret');
            $this->withConfirmedPassword()->post(route('superadmin.distribution.releases.key'))->assertSessionHas('error');
            $this->assertSame($secret, config('distribution.signing_secret'));
        } finally {
            @unlink($env);
        }
    }

    public function test_an_unwritable_env_gets_the_lines_to_add_by_hand(): void
    {
        $this->app->bind(\App\Services\Install\EnvWriter::class, fn () => new \App\Services\Install\EnvWriter('/nonexistent-dir/.env'));
        config(['distribution.signing_secret' => null]);
        $this->actingAs($this->root, 'staff');

        $this->withConfirmedPassword()->post(route('superadmin.distribution.releases.key'))
            ->assertSessionHas('error', __('distribution::distribution.keys_env_not_writable'))
            ->assertSessionHas('signing_env_lines', fn (string $lines) => str_contains($lines, 'DISTRIBUTION_SIGNING_SECRET='));

        $this->assertFalse(app(SigningKeys::class)->configured());
    }

    public function test_keygen_reports_an_existing_key_without_replacing_it(): void
    {
        $this->artisan('distribution:keygen')->expectsOutputToContain($this->publicKey)->assertSuccessful();
        $this->assertSame($this->publicKey, app(SigningKeys::class)->publicKey());
    }

    private function site(array $modules): Site
    {
        return app(SiteService::class)->create(['name' => 'Site '.$this->nextSeq()], $modules)['data'];
    }
}
