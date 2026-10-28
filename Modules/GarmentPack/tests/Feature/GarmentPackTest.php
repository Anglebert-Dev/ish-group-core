<?php

namespace Modules\GarmentPack\Tests\Feature;

use App\Services\Module\ModuleManager;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Services\StyleService;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Production\Models\WorkOrder;

class GarmentPackTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Production');
        app(ModuleManager::class)->install('GarmentPack');
    }

    private function scenario(): array
    {
        $owner = $this->actingAsStaff();
        [$shirt, [$medium, $large]] = $this->item($owner->business, ['M', 'L']);
        $medium->forceFill(['sale_price' => 6000])->save();
        $large->forceFill(['sale_price' => 6500])->save();
        [, [$fabric]] = $this->item($owner->business, [], ['type' => 'material', 'name' => 'Cotton', 'unit' => 'm']);
        [, [$buttons]] = $this->item($owner->business, [], ['type' => 'material', 'name' => 'Buttons', 'unit' => 'pcs']);
        $fabric->forceFill(['cost_price' => 2000])->save();
        $buttons->forceFill(['cost_price' => 50])->save();
        $store = $this->defaultStore($owner->business);

        $this->post(route('garmentpack.styles.store'), [
            'code' => 'SH-01', 'name' => 'School shirt', 'item' => $shirt->uuid, 'labour_cost' => 800, 'overhead_cost' => 200,
            'materials' => [
                ['variant' => $fabric->uuid, 'quantity' => 1.2, 'waste_percent' => 10],
                ['variant' => $buttons->uuid, 'quantity' => 7, 'waste_percent' => 0],
                ['variant' => '', 'quantity' => 3],
            ],
        ])->assertSessionHas('success');

        return compact('owner', 'shirt', 'medium', 'fabric', 'buttons', 'store') + ['style' => Style::query()->firstOrFail()];
    }

    public function test_style_costs_per_piece_and_margin_by_size(): void
    {
        $s = $this->scenario();
        $costing = app(StyleService::class)->costing($s['style']);

        // Cotton 1.2m + 10% = 1.32m × 2000 = 2640; buttons 7 × 50 = 350; + 800 + 200.
        $this->assertSame(2990.0, $costing['materials']);
        $this->assertSame(3990.0, $costing['total']);

        $this->get(route('garmentpack.styles.show', $s['style']))->assertOk()
            ->assertSee('RWF 3,990')
            ->assertSee('RWF 2,010')   // M: 6000 − 3990
            ->assertSee('RWF 2,510');  // L: 6500 − 3990
        $this->get(route('garmentpack.styles.index'))->assertOk()->assertSee('SH-01');
        $this->get(route('garmentpack.styles.edit', $s['style']))->assertOk()->assertSee('Cotton');
    }

    public function test_one_style_per_product_and_codes_unique(): void
    {
        $s = $this->scenario();

        $this->post(route('garmentpack.styles.store'), ['code' => 'SH-02', 'name' => 'Copy', 'item' => $s['shirt']->uuid])
            ->assertSessionHasErrors('item');
        [$trousers] = $this->item($s['owner']->business, [], ['name' => 'Trousers']);
        $this->post(route('garmentpack.styles.store'), ['code' => 'SH-01', 'name' => 'Trousers', 'item' => $trousers->uuid])
            ->assertSessionHasErrors('code');
    }

    public function test_work_order_issues_exactly_what_the_bill_of_materials_needs(): void
    {
        $s = $this->scenario();
        app(StockService::class)->receive($s['fabric'], $s['store'], 200);
        app(StockService::class)->receive($s['buttons'], $s['store'], 1000);

        $this->post(route('production.work-orders.store'), ['variant' => $s['medium']->uuid, 'store' => $s['store']->uuid, 'quantity' => 100]);
        $wo = WorkOrder::query()->firstOrFail();

        $this->get(route('production.work-orders.show', $wo))->assertOk()->assertSee('SH-01')->assertSee('132');

        $this->post(route('garmentpack.work-orders.issue', $wo), ['store' => $s['store']->uuid])->assertSessionHas('success');
        $this->assertSame(68.0, app(StockService::class)->onHand($s['fabric']));    // 200 − 132
        $this->assertSame(300.0, app(StockService::class)->onHand($s['buttons']));  // 1000 − 700

        $this->post(route('garmentpack.work-orders.issue', $wo), ['store' => $s['store']->uuid])
            ->assertSessionHas('error', __('garmentpack::garment.nothing_to_issue'));
    }

    public function test_issue_is_refused_without_enough_material(): void
    {
        $s = $this->scenario();
        app(StockService::class)->receive($s['fabric'], $s['store'], 10);
        app(StockService::class)->receive($s['buttons'], $s['store'], 1000);
        $this->post(route('production.work-orders.store'), ['variant' => $s['medium']->uuid, 'store' => $s['store']->uuid, 'quantity' => 100]);
        $wo = WorkOrder::query()->firstOrFail();

        $this->post(route('garmentpack.work-orders.issue', $wo), ['store' => $s['store']->uuid])->assertSessionHas('error');
        $this->assertSame(1000.0, app(StockService::class)->onHand($s['buttons']), 'all or nothing');
    }
}
