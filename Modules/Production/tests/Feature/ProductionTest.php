<?php

namespace Modules\Production\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use App\Support\Settings\BusinessSettings;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Production\Enums\WorkOrderStatus;
use Modules\Production\Models\ProductionEntry;
use Modules\Production\Models\WorkOrder;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;

class ProductionTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Production');
    }

    /**
     * Signed-in owner and a work order for 100 shirts (M) through Cutting → Sewing → Finishing → Packing.
     */
    private function workOrder(array $overrides = []): array
    {
        $owner = $this->actingAsStaff();
        [$item, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);

        $this->post(route('production.work-orders.store'), $overrides + [
            'variant' => $medium->uuid, 'store' => $store->uuid, 'quantity' => 100,
            'line' => 'Line A', 'daily_target' => 40, 'due_date' => now()->addWeek()->toDateString(),
        ])->assertSessionHas('success');

        return [WorkOrder::query()->firstOrFail(), $medium, $store, $owner];
    }

    private function record(WorkOrder $wo, int $stage, float $quantity, ?string $note = null)
    {
        return $this->post(route('production.work-orders.record', $wo), array_filter(['stage' => $stage, 'quantity' => $quantity, 'note' => $note], fn ($v) => $v !== null));
    }

    public function test_counts_flow_through_stages_and_finished_pieces_enter_stock(): void
    {
        [$wo, $medium] = $this->workOrder();
        $this->assertSame('WO-'.now()->format('Y').'-00001', $wo->number);
        $this->assertSame(['Cutting', 'Sewing', 'Finishing', 'Packing'], $wo->stages);

        $this->record($wo, 0, 100)->assertSessionHas('success');
        $this->record($wo, 1, 60)->assertSessionHas('success');
        $this->record($wo, 2, 50)->assertSessionHas('success');
        $this->record($wo, 3, 30)->assertSessionHas('success');

        $wo->refresh();
        $this->assertSame([100, 60, 50, 30], array_map('intval', $wo->counts));
        $this->assertSame(WorkOrderStatus::InProgress, $wo->status);
        $this->assertSame(30.0, app(StockService::class)->onHand($medium));

        // Only the new finished pieces go in.
        $this->record($wo, 3, 15)->assertSessionHas('success');
        $this->assertSame(45.0, app(StockService::class)->onHand($medium));
        $this->assertSame([30.0, 15.0], Movement::query()->where('type', 'production_in')->orderBy('id')->pluck('quantity')->all());

        $this->get(route('production.work-orders.show', $wo))->assertOk()->assertSee('Packing')->assertSee('Line A');
        $this->get(route('production.work-orders.index'))->assertOk()->assertSee($wo->number)->assertSee(__('production::production.today'));
    }

    public function test_a_later_stage_can_never_exceed_an_earlier_one(): void
    {
        [$wo] = $this->workOrder();

        $this->record($wo, 1, 1)->assertSessionHas('error', 'Sewing cannot be more than Cutting (0).');
        $this->record($wo, 0, 101)->assertSessionHas('error', 'Cutting cannot be more than the quantity to make (100).');
        $this->record($wo, 0, 50);
        $this->record($wo, 1, 40);
        $this->record($wo, 2, 41)->assertSessionHas('error', 'Finishing cannot be more than Sewing (40).');

        $this->assertSame([50, 40, 0, 0], array_map('intval', $wo->fresh()->counts));
    }

    public function test_corrections_need_a_note_and_cannot_undercut_the_next_stage(): void
    {
        [$wo] = $this->workOrder();
        $this->record($wo, 0, 50);
        $this->record($wo, 1, 40);

        $this->record($wo, 0, -5)->assertSessionHas('error', __('production::production.correction_needs_note'));
        $this->record($wo, 0, -20, 'Miscounted')->assertSessionHas('error', 'Cutting cannot be less than Sewing (40).');
        $this->record($wo, 0, -10, 'Miscounted')->assertSessionHas('success');

        $this->assertSame(40.0, $wo->fresh()->count(0));
        $this->assertSame(3, ProductionEntry::query()->count(), 'refused counts leave no entry');
    }

    public function test_lowering_finished_reverses_stock_or_is_refused_when_already_sold(): void
    {
        [$wo, $medium, $store] = $this->workOrder();
        foreach ([0, 1, 2, 3] as $stage) {
            $this->record($wo, $stage, 20);
        }
        $this->assertSame(20.0, app(StockService::class)->onHand($medium));

        $this->record($wo, 3, -5, 'Counted twice')->assertSessionHas('success');
        $this->assertSame(15.0, app(StockService::class)->onHand($medium), 'correction took the pieces back out');

        app(StockService::class)->issue($medium, $store, 12, 'Sold', MovementType::Sale);
        $this->record($wo, 3, -10, 'Wrong')->assertSessionHas('error');
        $this->assertSame(15.0, $wo->fresh()->finished(), 'count unchanged');
        $this->assertSame(3.0, app(StockService::class)->onHand($medium));
    }

    public function test_finishing_everything_completes_the_work_order(): void
    {
        [$wo] = $this->workOrder(['quantity' => 10]);
        foreach ([0, 1, 2, 3] as $stage) {
            $this->record($wo, $stage, 10);
        }

        $wo->refresh();
        $this->assertSame(WorkOrderStatus::Completed, $wo->status);
        $this->record($wo, 3, 1)->assertSessionHas('error', __('production::production.closed'));
    }

    public function test_materials_are_issued_to_the_work_order(): void
    {
        [$wo, , $store, $owner] = $this->workOrder();
        [, [$fabric]] = $this->item($owner->business, [], ['type' => 'material', 'name' => 'Cotton', 'unit' => 'm']);
        app(StockService::class)->receive($fabric, $store, 200);

        $this->post(route('production.work-orders.materials', $wo), [
            'store' => $store->uuid,
            'materials' => [['variant' => $fabric->uuid, 'quantity' => 120]],
        ])->assertSessionHas('success');

        $this->assertSame(80.0, app(StockService::class)->onHand($fabric));
        $this->get(route('production.work-orders.show', $wo))->assertSee('Cotton');

        $this->post(route('production.work-orders.materials', $wo), [
            'store' => $store->uuid, 'materials' => [['variant' => $fabric->uuid, 'quantity' => 100]],
        ])->assertSessionHas('error');
    }

    public function test_linked_sales_order_follows_production(): void
    {
        app(ModuleManager::class)->install('SalesOrders');
        $owner = $this->actingAsStaff();
        [, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);
        $customer = new Customer(['type' => 'person', 'name' => 'Aline']);
        $customer->business_id = $owner->business_id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        $this->post(route('salesorders.orders.store'), [
            'customer' => $customer->uuid, 'store' => $store->uuid, 'order_date' => now()->toDateString(),
            'lines' => [['variant' => $medium->uuid, 'quantity' => 10, 'unit_price' => 5000]],
        ]);
        $order = SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        $this->get(route('production.work-orders.create', ['sales_order' => $order->uuid]))->assertOk()->assertSee($order->number);
        $this->post(route('production.work-orders.store'), [
            'variant' => $medium->uuid, 'store' => $store->uuid, 'quantity' => 10, 'sales_order' => $order->uuid,
        ]);
        $wo = WorkOrder::query()->firstOrFail();
        $this->assertSame($order->id, $wo->sales_order_id);

        $this->record($wo, 0, 10);
        $this->assertSame(OrderStatus::InProduction, $order->fresh()->status);

        foreach ([1, 2, 3] as $stage) {
            $this->record($wo, $stage, 10);
        }
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $this->get(route('salesorders.orders.show', $order))->assertSee($wo->number);
        $this->post(route('salesorders.orders.deliver', $order))->assertSessionHas('success');
        $this->assertSame(0.0, app(StockService::class)->onHand($medium));
    }

    public function test_each_business_sets_its_own_stages(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('production.settings.update'), ['stages' => "Cutting\nSewing\nQC\nIroning"])->assertSessionHas('success');
        $this->assertSame(['Cutting', 'Sewing', 'QC', 'Ironing'], app(BusinessSettings::class)->get('production.stages', null, $owner->business));

        [, [$variant]] = $this->item($owner->business);
        $this->post(route('production.work-orders.store'), ['variant' => $variant->uuid, 'store' => $this->defaultStore($owner->business)->uuid, 'quantity' => 5]);
        $this->assertSame(['Cutting', 'Sewing', 'QC', 'Ironing'], WorkOrder::query()->firstOrFail()->stages);
    }

    public function test_cancel_and_isolation(): void
    {
        [$wo] = $this->workOrder();
        $this->withConfirmedPassword()->post(route('production.work-orders.cancel', $wo), ['reason' => 'Order dropped'])->assertSessionHas('success');
        $this->assertSame(WorkOrderStatus::Cancelled, $wo->fresh()->status);

        $this->flushSession();
        $this->actingAsStaff();
        $this->get(route('production.work-orders.show', $wo))->assertNotFound();
    }
}
