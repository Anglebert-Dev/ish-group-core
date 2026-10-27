<?php

namespace Tests\Feature;

use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uses the bundled Frontend module as the fixture.
 */
class ModuleSystemTest extends TestCase
{
    private string $statusesFile;

    private string $statuses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusesFile = base_path('modules_statuses.json');
        $this->statuses = file_get_contents($this->statusesFile);
    }

    protected function tearDown(): void
    {
        file_put_contents($this->statusesFile, $this->statuses);

        parent::tearDown();
    }

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
        $this->assertSame('1.1.0', $modules->installedVersion('Frontend'));
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
        $this->assertTrue(Schema::hasTable('customers'));

        $modules->toggle('Frontend', true);
        $this->assertTrue($modules->isActive('Frontend'));

        $modules->uninstall('Frontend', purgeData: true);
        $this->assertFalse($modules->isInstalled('Frontend'));
        $this->assertFalse(Schema::hasTable('customers'));
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
}
