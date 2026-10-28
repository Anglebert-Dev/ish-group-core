<?php

namespace Modules\Inventory\Tests\Feature;

use App\Services\Module\ModuleManager;

/**
 * Inventory is the one switch for the whole stock system.
 */
class InventoryFamilyTest extends InventoryTestCase
{
    public function test_turning_inventory_off_turns_off_every_module_built_on_it(): void
    {
        $statuses = file_get_contents(base_path('modules_statuses.json'));
        $this->beforeApplicationDestroyed(fn () => file_put_contents(base_path('modules_statuses.json'), $statuses));

        $modules = app(ModuleManager::class);
        foreach (['SalesOrders', 'Production', 'Invoicing', 'Quotations', 'Delivery', 'Purchasing', 'GarmentPack'] as $name) {
            $this->assertTrue($modules->install($name)['status'], $name);
        }

        $family = ['SalesOrders', 'Production', 'Invoicing', 'Quotations', 'Delivery', 'Purchasing', 'GarmentPack'];
        $this->assertEqualsCanonicalizing($family, $modules->allDependents('Inventory'));

        $super = $this->makeSuperAdmin();
        $this->actingAs($super, 'staff');
        $this->get(route('superadmin.modules.index'))->assertOk()->assertSee(__('body.module_disable_cascade', ['modules' => implode(', ', $modules->allDependents('Inventory'))]), false);

        $this->withConfirmedPassword()->post(route('superadmin.modules.toggle', 'Inventory'), ['enabled' => 0])->assertSessionHas('success');
        $modules->flush();

        foreach (['Inventory', ...$family] as $name) {
            $this->assertFalse($modules->isActive($name), $name.' should be off');
            $this->assertTrue($modules->isInstalled($name), $name.' keeps its data');
        }

        // Turning one back on brings what it needs.
        $this->assertTrue($modules->toggle('SalesOrders', true)['status']);
        $this->assertTrue($modules->isActive('Inventory'));
        $this->assertTrue($modules->isActive('SalesOrders'));
        $this->assertFalse($modules->isActive('Production'));
    }
}
