<?php

namespace Tests\Feature;

use App\Services\Module\ModuleManager;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\Support\Facades\Schema;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

/**
 * Uses the bundled Frontend module as the fixture.
 */
class ModuleSystemTest extends TestCase
{

    public function test_module_is_inactive_until_installed(): void
    {
        $modules = app(ModuleManager::class);

        $this->assertTrue($modules->exists('Frontend'));
        $this->assertFalse($modules->isInstalled('Frontend'));
        $this->assertFalse($modules->isActive('Frontend'));
        $this->assertSame([], $modules->hook('landing_page'));
        $this->get('/s/anything/about')->assertNotFound();
    }

    public function test_install_runs_migrations_and_activates_hooks(): void
    {
        $modules = app(ModuleManager::class);

        $this->assertTrue($modules->install('Frontend')['status']);

        $this->assertTrue($modules->isActive('Frontend'));
        $this->assertSame('1.3.0', $modules->installedVersion('Frontend'));
        $this->assertTrue(Schema::hasTable('customers'));
        $this->assertArrayHasKey('Frontend', $modules->hook('user_permissions'));
        $this->assertFalse($modules->install('Frontend')['status'], 'second install is refused');
    }

    public function test_disable_keeps_data_and_uninstall_can_purge(): void
    {
        $modules = app(ModuleManager::class);
        $modules->install('Frontend');

        $modules->toggle('Frontend', false);
        $this->assertFalse($modules->isActive('Frontend'));
        $this->assertTrue($modules->isInstalled('Frontend'));
        $this->assertTrue(Schema::hasTable('frontend_sites'));

        $modules->toggle('Frontend', true);
        $this->assertTrue($modules->isActive('Frontend'));

        $modules->uninstall('Frontend', purgeData: true);
        $this->assertFalse($modules->isInstalled('Frontend'));
        $this->assertFalse(Schema::hasTable('frontend_sites'));
        // Customers are core data and outlive the website module.
        $this->assertTrue(Schema::hasTable('customers'));
    }

    public function test_superadmin_installs_from_the_modules_page(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->withConfirmedPassword()
            ->post(route('superadmin.modules.install', 'Frontend'))
            ->assertRedirect(route('superadmin.modules.index'))
            ->assertSessionHas('success');

        $this->assertTrue(app(ModuleManager::class)->isActive('Frontend'));
    }

    public function test_module_cli_installs_and_lists(): void
    {
        $this->artisan('core:module', ['action' => 'install', 'module' => 'Frontend'])->assertSuccessful();
        $this->artisan('core:module', ['action' => 'list'])->assertSuccessful();

        $this->assertTrue(app(ModuleManager::class)->isActive('Frontend'));
    }

    public function test_business_can_have_a_module_switched_off(): void
    {
        app(ModuleManager::class)->install('Frontend');
        [$business, $owner] = $this->makeBusiness();
        $business->forceFill(['enabled_modules' => []])->save();

        $this->actingAs($owner, 'staff');

        $this->assertSame([], app(ModuleManager::class)->hook('modifyAdminMenu'));
        $this->get(route('frontend.admin.website.edit'))->assertNotFound();
    }

    /**
     * Frontend's module.json temporarily says "requires": ["billing"].
     */
    private function frontendRequiresBilling(): void
    {
        $file = Module::find('Frontend')->getPath().'/module.json';
        $original = file_get_contents($file);
        $this->beforeApplicationDestroyed(fn () => file_put_contents($file, $original));

        file_put_contents($file, json_encode(['requires' => ['billing']] + json_decode($original, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function test_a_module_cannot_be_installed_or_enabled_without_its_requirements(): void
    {
        $this->frontendRequiresBilling();
        $modules = app(ModuleManager::class);

        $this->assertSame(['Billing'], $modules->requires('Frontend'));
        $this->assertFalse($modules->install('Frontend')['status']);
        $this->assertFalse($modules->isInstalled('Frontend'));

        $this->assertTrue($modules->install('Billing')['status']);
        $this->assertTrue($modules->install('Frontend')['status']);

        // Uninstalling a required module is refused (it could remove data)…
        $this->assertFalse($modules->uninstall('Billing')['status']);

        // …but switching it off switches off what needs it too: one switch for the family.
        $result = $modules->toggle('Billing', false);
        $this->assertTrue($result['status']);
        $this->assertStringContainsString('Frontend', $result['message']);
        $this->assertFalse($modules->isActive('Billing'));
        $this->assertFalse($modules->isActive('Frontend'));
        $this->assertTrue($modules->isInstalled('Frontend'), 'data kept');

        // Switching the dependent back on brings its requirement back.
        $this->assertTrue($modules->toggle('Frontend', true)['status']);
        $this->assertTrue($modules->isActive('Billing'));
        $this->assertTrue($modules->isActive('Frontend'));
    }

    public function test_a_business_only_uses_a_module_with_its_requirements(): void
    {
        $this->frontendRequiresBilling();
        [$business] = $this->makeBusiness();

        $business->forceFill(['enabled_modules' => ['Frontend']])->save();
        $this->assertFalse($business->fresh()->canUseModule('Frontend'));

        $business->forceFill(['enabled_modules' => ['Frontend', 'Billing']])->save();
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));
    }

    public function test_switching_a_module_on_for_a_business_brings_its_requirements(): void
    {
        $this->frontendRequiresBilling();
        [$business] = $this->makeBusiness();

        app(PlatformService::class)->updateEnabledModules($business, ['Frontend']);

        $this->assertEqualsCanonicalizing(['Frontend', 'Billing'], $business->fresh()->enabled_modules);
        $this->assertEqualsCanonicalizing(['Frontend', 'Billing'], app(ModuleManager::class)->withRequirements(['Frontend']));
    }

    public function test_on_off_is_stored_in_the_database_not_in_files(): void
    {
        $modules = app(ModuleManager::class);
        $modules->install('Frontend');

        $modules->toggle('Frontend', false);

        $this->assertSame('0', \App\Models\System\System::getProperty('frontend_enabled'));
        $this->assertFileDoesNotExist(base_path('modules_statuses.json'));
        $this->assertFalse(app(ModuleManager::class)->isActive('Frontend'));

        $modules->toggle('Frontend', true);
        $this->assertTrue(app(ModuleManager::class)->isActive('Frontend'));
    }
}
