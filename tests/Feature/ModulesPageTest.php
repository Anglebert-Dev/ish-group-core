<?php

namespace Tests\Feature;

use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use Tests\TestCase;

/**
 * Super Admin → Modules: categories, a page for each module, and what can
 * hide a running module from your own menu.
 */
class ModulesPageTest extends TestCase
{
    public function test_modules_are_grouped_by_category_with_a_page_for_each(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.modules.index'))->assertOk()
            ->assertSee(__('body.module_category_sales'))->assertSee(__('body.module_category_platform'))
            ->assertSee(route('superadmin.modules.show', 'Savings'), false);

        foreach (app(ModuleManager::class)->catalogue() as $module) {
            $this->get(route('superadmin.modules.show', $module['name']))->assertOk()->assertSee($module['name']);
        }

        $this->get(route('superadmin.modules.show', 'Nothing'))->assertNotFound();
    }

    public function test_a_module_says_what_it_needs_and_what_it_adds(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.modules.show', 'Delivery'))->assertOk()
            ->assertSee('SalesOrders')->assertSee(__('body.module_needs_first', ['modules' => 'SalesOrders']))
            ->assertSee('delivery_notes')->assertSee('Delivery notes')->assertDontSee(route('superadmin.modules.install', 'Delivery'), false);
    }

    public function test_a_free_module_needs_no_switch_and_shows_up_without_billing(): void
    {
        app(ModuleManager::class)->install('Distribution');
        $owner = $this->makeSuperAdmin();
        $owner->business->forceFill(['enabled_modules' => ['Savings']])->save();
        $this->actingAs($owner->fresh(), 'staff');

        $this->assertTrue($owner->fresh()->business->canUseModule('Distribution'));
        $this->get(route('superadmin.modules.index'))->assertOk()->assertSee(route('superadmin.distribution.sites.index'), false);
        $this->get(route('superadmin.distribution.sites.index'))->assertOk();
        $this->get(route('superadmin.modules.show', 'Distribution'))->assertOk()->assertSee(__('body.module_open'));
    }

    public function test_a_module_switched_off_for_your_business_says_so_and_where_to_fix_it(): void
    {
        app(ModuleManager::class)->install('Savings');
        $owner = $this->makeSuperAdmin();
        $owner->business->forceFill(['enabled_modules' => []])->save();
        $this->actingAs($owner->fresh(), 'staff');

        $this->assertInstanceOf(Business::class, $owner->business);
        $this->get(route('superadmin.modules.index'))->assertOk()->assertSee(__('body.module_off_here'));
        $this->get(route('superadmin.modules.show', 'Savings'))->assertOk()
            ->assertSee(__('body.module_off_here_button'))->assertDontSee(__('body.module_open'));
    }

    public function test_an_action_returns_to_the_page_it_came_from(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->withConfirmedPassword();

        $this->from(route('superadmin.modules.show', 'Savings'))->post(route('superadmin.modules.install', 'Savings'))
            ->assertRedirect(route('superadmin.modules.show', 'Savings'));
    }
}
