<?php

namespace Modules\Inventory\Tests\Feature;

use App\Support\Settings\BusinessSettings;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\StockService;

class InventoryScreensTest extends InventoryTestCase
{
    private function shirtPayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'finished',
            'name' => 'School shirt',
            'sku' => 'SHIRT',
            'category' => 'Uniforms',
            'unit' => 'pcs',
            'reorder_level' => 10,
            'option1_name' => 'Size',
            'option2_name' => 'Colour',
            'variants' => [
                ['option1' => 'M', 'option2' => 'White', 'sku' => 'SH-M-W', 'cost_price' => 3000, 'sale_price' => 5000, 'opening' => 20],
                ['option1' => 'L', 'option2' => 'White', 'sku' => 'SH-L-W', 'cost_price' => 3200, 'sale_price' => 5500, 'opening' => 0],
            ],
        ];
    }

    public function test_new_businesses_get_a_default_store(): void
    {
        [$business] = $this->makeBusiness();

        $store = Store::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertTrue($store->is_default);
        $this->assertSame('Main store', $store->name);
    }

    public function test_staff_add_an_item_with_variants_and_opening_stock(): void
    {
        $owner = $this->actingAsStaff();

        $this->get(route('inventory.items.create'))->assertOk();
        $this->post(route('inventory.items.store'), $this->shirtPayload())->assertRedirect()->assertSessionHas('success');

        $item = Item::query()->firstOrFail();
        $this->assertSame($owner->business_id, $item->business_id);
        $this->assertSame(['M / White', 'L / White'], $item->variants->map->optionLabel()->all());

        $medium = $item->variants->first();
        $this->assertSame(20.0, app(StockService::class)->onHand($medium));
        $this->assertSame('opening', Movement::query()->value('type')->value);

        $this->get(route('inventory.items.index'))->assertOk()->assertSee('School shirt')->assertSee('20');
        $this->get(route('inventory.items.show', $item))->assertOk()->assertSee('M / White')->assertSee('Main store');
        $this->get(route('inventory.items.edit', $item))->assertOk()->assertSee('SH-L-W');
        $this->get(route('inventory.items.export.pdf'))->assertOk();
        $this->get(route('inventory.items.export.excel'))->assertOk();
    }

    public function test_an_item_without_options_has_one_variant(): void
    {
        $this->actingAsStaff();

        $this->post(route('inventory.items.store'), [
            'type' => 'material', 'name' => 'Cotton fabric', 'unit' => 'm',
            'variants' => [['cost_price' => 2500, 'opening' => 120.5], ['cost_price' => 1, 'opening' => 999]],
        ])->assertSessionHasNoErrors();

        $item = Item::query()->firstOrFail();
        $this->assertCount(1, $item->variants);
        $this->assertSame(120.5, app(StockService::class)->itemOnHand($item));
    }

    public function test_items_with_options_need_distinct_variant_rows(): void
    {
        $this->actingAsStaff();

        $this->post(route('inventory.items.store'), $this->shirtPayload(['variants' => []]))->assertSessionHasErrors('variants');
        $this->post(route('inventory.items.store'), $this->shirtPayload(['variants' => [
            ['option1' => 'M', 'option2' => 'White'], ['option1' => 'm', 'option2' => 'white '],
        ]]))->assertSessionHasErrors('variants');
    }

    public function test_stock_actions_from_the_item_page(): void
    {
        $owner = $this->actingAsStaff();
        $this->post(route('inventory.items.store'), $this->shirtPayload());
        $item = Item::query()->firstOrFail();
        $medium = $item->variants->first();
        $main = $this->defaultStore($owner->business);
        $shop = $this->store($owner->business, 'Shop');

        $post = fn (array $data) => $this->post(route('inventory.items.stock', $item), $data + ['variant' => $medium->uuid, 'store' => $main->uuid]);

        $post(['action' => 'receive', 'quantity' => 10, 'unit_cost' => 3000])->assertSessionHas('success');
        $post(['action' => 'transfer', 'to_store' => $shop->uuid, 'quantity' => 5])->assertSessionHas('success');
        $post(['action' => 'issue', 'quantity' => 2])->assertSessionHasErrors('reason');
        $post(['action' => 'issue', 'quantity' => 500, 'reason' => 'Lost'])->assertSessionHas('error');
        $post(['action' => 'adjust', 'quantity' => 24, 'reason' => 'Count'])->assertSessionHas('success');

        $stock = app(StockService::class);
        $this->assertSame(24.0, $stock->onHand($medium, $main));
        $this->assertSame(5.0, $stock->onHand($medium, $shop));

        $this->get(route('inventory.movements.index'))->assertOk()->assertSee('Correction')->assertSee('Count');
        $this->get(route('inventory.movements.index', ['item' => $item->uuid, 'type' => 'transfer_in']))->assertOk()->assertSee('Shop');
        $this->get(route('inventory.movements.export.excel'))->assertOk();
    }

    public function test_items_with_history_are_deactivated_not_deleted(): void
    {
        $this->actingAsStaff();
        $this->post(route('inventory.items.store'), $this->shirtPayload());
        $item = Item::query()->firstOrFail();

        $this->withConfirmedPassword()->delete(route('inventory.items.destroy', $item))->assertSessionHas('error');
        $this->assertNotNull($item->fresh());

        $this->put(route('inventory.items.status', $item), ['active' => 0])->assertSessionHas('success');
        $this->assertFalse($item->fresh()->is_active);
        $this->get(route('inventory.items.index'))->assertDontSee('School shirt');
        $this->get(route('inventory.items.index', ['status' => 'inactive']))->assertSee('School shirt');
    }

    public function test_unused_items_can_be_deleted(): void
    {
        $this->actingAsStaff();
        $this->post(route('inventory.items.store'), $this->shirtPayload(['variants' => [['option1' => 'M']]]));
        $item = Item::query()->firstOrFail();

        $this->withConfirmedPassword()->delete(route('inventory.items.destroy', $item))->assertRedirect(route('inventory.items.index'));
        $this->assertSoftDeleted($item);
    }

    public function test_removing_a_variant_keeps_one_with_history(): void
    {
        $this->actingAsStaff();
        $this->post(route('inventory.items.store'), $this->shirtPayload());
        $item = Item::query()->firstOrFail();
        [$medium, $large] = $item->variants->all();

        // Keep neither row; add XL.
        $this->put(route('inventory.items.update', $item), $this->shirtPayload(['variants' => [['option1' => 'XL', 'option2' => 'White']]]))
            ->assertSessionHas('success');

        $this->assertFalse($medium->fresh()->is_active, 'M has stock history: kept, inactive');
        $this->assertSoftDeleted($large);
        $this->assertSame(['XL / White'], $item->fresh()->variants->where('is_active', true)->map->optionLabel()->values()->all());
    }

    public function test_low_stock_filter(): void
    {
        $owner = $this->actingAsStaff();
        $this->post(route('inventory.items.store'), $this->shirtPayload());
        $this->post(route('inventory.items.store'), [
            'type' => 'material', 'name' => 'Thread', 'unit' => 'pcs', 'reorder_level' => 5, 'variants' => [['opening' => 100]],
        ]);

        // L / White has 0 against reorder level 10.
        $this->get(route('inventory.items.index', ['stock' => 'low']))->assertSee('School shirt')->assertDontSee('Thread');
        $this->get(route('dashboard'))->assertOk()->assertSee(__('inventory::inventory.dashboard_low'));
    }

    public function test_businesses_only_see_their_own_inventory(): void
    {
        [$other] = $this->makeBusiness();
        [$theirs] = $this->item($other);
        $this->actingAsStaff();

        $this->get(route('inventory.items.index'))->assertDontSee('School shirt');
        $this->get(route('inventory.items.show', $theirs))->assertNotFound();
        $this->post(route('inventory.items.stock', $theirs), ['action' => 'receive', 'variant' => 'x', 'store' => 'y', 'quantity' => 1])->assertNotFound();
    }

    public function test_members_need_inventory_permissions(): void
    {
        [$business] = $this->makeBusiness();
        [$item, [$variant]] = $this->item($business);

        $this->actingAs($this->makeMember($business, ['list inventory.item', 'read inventory.item']), 'staff');
        $this->get(route('inventory.items.show', $item))->assertOk()->assertDontSee(__('inventory::inventory.stock_action'));
        $this->post(route('inventory.items.stock', $item), [
            'action' => 'receive', 'variant' => $variant->uuid, 'store' => $this->defaultStore($business)->uuid, 'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_stores_can_be_added_made_default_and_protected(): void
    {
        $owner = $this->actingAsStaff();
        $main = $this->defaultStore($owner->business);

        $this->post(route('inventory.stores.store'), ['name' => 'Kimironko shop', 'code' => 'KIM'])->assertSessionHas('success');
        $shop = Store::query()->where('name', 'Kimironko shop')->firstOrFail();
        $this->get(route('inventory.stores.index'))->assertOk()->assertSee('Kimironko shop');

        $this->put(route('inventory.stores.default', $shop))->assertSessionHas('success');
        $this->assertTrue($shop->fresh()->is_default);
        $this->assertFalse($main->fresh()->is_default);

        $this->put(route('inventory.stores.update', $shop), ['name' => 'Kimironko shop', 'is_active' => 0])->assertSessionHas('error');
        $this->withConfirmedPassword()->delete(route('inventory.stores.destroy', $shop))->assertSessionHas('error');

        [, [$variant]] = $this->item($owner->business);
        app(StockService::class)->receive($variant, $main, 1);
        $this->withConfirmedPassword()->delete(route('inventory.stores.destroy', $main))->assertSessionHas('error');
        $this->assertNotNull($main->fresh());
    }

    public function test_units_come_from_business_settings(): void
    {
        $owner = $this->actingAsStaff();

        $this->get(route('inventory.settings.edit'))->assertOk()->assertSee('dozen');
        $this->put(route('inventory.settings.update'), ['units' => "pcs\nbundle\n\nbundle"])->assertSessionHas('success');

        $this->assertSame(['pcs', 'bundle'], app(BusinessSettings::class)->get('inventory.units', null, $owner->business));
        $this->post(route('inventory.items.store'), ['type' => 'finished', 'name' => 'Rope', 'unit' => 'm'])->assertSessionHasErrors('unit');
        $this->post(route('inventory.items.store'), ['type' => 'finished', 'name' => 'Rope', 'unit' => 'bundle'])->assertSessionHasNoErrors();
    }

    public function test_sidebar_shows_the_inventory_section(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('inventory.items.index'), false)
            ->assertSee(route('inventory.stores.index'), false)
            ->assertSee(route('inventory.movements.index'), false);
    }
}
