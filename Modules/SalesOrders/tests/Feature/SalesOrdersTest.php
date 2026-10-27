<?php

namespace Modules\SalesOrders\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Business\Business;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;

class SalesOrdersTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('SalesOrders');
    }

    private function customer(Business $business, string $name = 'Green Hills School'): Customer
    {
        $customer = new Customer(['type' => 'company', 'name' => $name, 'company_name' => $name]);
        $customer->business_id = $business->id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        return $customer;
    }

    /**
     * Owner signed in, a customer, a shirt in M/L with $stock pieces of M in the main store.
     */
    private function scenario(float $stock = 100): array
    {
        $owner = $this->actingAsStaff();
        $business = $owner->business;
        $customer = $this->customer($business);
        [$item, [$medium, $large]] = $this->item($business, ['M', 'L']);
        $store = $this->defaultStore($business);
        if ($stock > 0) {
            app(StockService::class)->receive($medium, $store, $stock);
        }

        return compact('owner', 'business', 'customer', 'item', 'medium', 'large', 'store');
    }

    private function payload(array $s, array $overrides = []): array
    {
        return $overrides + [
            'customer' => $s['customer']->uuid,
            'store' => $s['store']->uuid,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'discount' => 1000,
            'lines' => [
                ['variant' => $s['medium']->uuid, 'quantity' => 30, 'unit_price' => 5000],
                ['variant' => $s['large']->uuid, 'quantity' => 0, 'unit_price' => 5500],
                ['variant' => '', 'quantity' => 4],
            ],
        ];
    }

    public function test_order_lifecycle_from_pending_to_delivered(): void
    {
        $s = $this->scenario();
        $year = now()->format('Y');

        $this->post(route('salesorders.orders.store'), $this->payload($s))->assertSessionHasErrors('lines.1.quantity');

        $payload = $this->payload($s);
        unset($payload['lines'][1]);
        $this->post(route('salesorders.orders.store'), $payload)->assertRedirect()->assertSessionHas('success');

        $order = SalesOrder::query()->firstOrFail();
        $this->assertSame("SO-{$year}-00001", $order->number);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('150000.00', $order->subtotal);
        $this->assertSame('149000.00', $order->total);

        $this->get(route('salesorders.orders.index'))->assertOk()->assertSee($order->number)->assertSee('Green Hills School');
        $this->get(route('salesorders.orders.show', $order))->assertOk()->assertSee('School shirt (M)');

        $this->post(route('salesorders.orders.confirm', $order))->assertSessionHas('success');
        $this->assertSame(30.0, app(StockService::class)->reserved([$s['medium']->id])[$s['medium']->id]);
        $this->get(route('inventory.items.show', $s['item']))->assertSee(__('inventory::inventory.reserved'));

        $this->put(route('salesorders.orders.progress', $order), ['status' => 'in_production'])->assertSessionHas('success');
        $this->put(route('salesorders.orders.progress', $order), ['status' => 'ready'])->assertSessionHas('success');

        $this->post(route('salesorders.orders.deliver', $order))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(70.0, app(StockService::class)->onHand($s['medium']));
        $this->assertSame([], app(StockService::class)->reserved([$s['medium']->id]), 'delivered orders reserve nothing');

        $movement = Movement::query()->where('type', 'sale')->firstOrFail();
        $this->assertSame($order->number, $movement->reference_label);
        $this->assertTrue($movement->reference->is($order));

        $this->post(route('salesorders.orders.deliver', $order))->assertSessionHas('error');
        $this->withConfirmedPassword()->post(route('salesorders.orders.cancel', $order), ['reason' => 'x'])->assertSessionHas('error');
    }

    public function test_lines_can_only_change_while_pending(): void
    {
        $s = $this->scenario();
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['lines' => [['variant' => $s['medium']->uuid, 'quantity' => 5, 'unit_price' => 5000]]]));
        $order = SalesOrder::query()->firstOrFail();

        $edit = $this->payload($s, ['lines' => [['variant' => $s['medium']->uuid, 'quantity' => 8, 'unit_price' => 5000]]]);
        $this->put(route('salesorders.orders.update', $order), $edit)->assertSessionHas('success');
        $this->assertSame(8.0, $order->lines()->first()->quantity, 'pending: lines editable');

        $this->post(route('salesorders.orders.confirm', $order));

        $edit['lines'][0]['quantity'] = 50;
        $edit['due_date'] = now()->addMonth()->toDateString();
        $this->put(route('salesorders.orders.update', $order), $edit)->assertSessionHas('success');
        $this->assertSame(8.0, $order->lines()->first()->quantity, 'confirmed: lines ignored');
        $this->assertSame(now()->addMonth()->toDateString(), $order->fresh()->due_date->toDateString(), 'header still editable');
        $this->get(route('salesorders.orders.edit', $order))->assertOk()->assertSee(__('salesorders::orders.lines_locked_hint'));
    }

    public function test_delivery_needs_enough_stock(): void
    {
        $s = $this->scenario(10);
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['lines' => [
            ['variant' => $s['medium']->uuid, 'quantity' => 6, 'unit_price' => 1],
            ['variant' => $s['medium']->uuid, 'quantity' => 6, 'unit_price' => 1],
        ]]));
        $order = SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        $this->post(route('salesorders.orders.deliver', $order))
            ->assertSessionHas('error', 'Not enough School shirt (M) in Main store: 10 available, 12 needed.');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(10.0, app(StockService::class)->onHand($s['medium']));
    }

    public function test_cancelling_releases_reserved_stock(): void
    {
        $s = $this->scenario();
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['lines' => [['variant' => $s['medium']->uuid, 'quantity' => 5, 'unit_price' => 1]]]));
        $order = SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        $this->post(route('salesorders.orders.cancel', $order), ['reason' => 'Changed mind'])->assertRedirect(); // needs password
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);

        $this->withConfirmedPassword()->post(route('salesorders.orders.cancel', $order), ['reason' => 'Changed mind'])->assertSessionHas('success');
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame([], app(StockService::class)->reserved([$s['medium']->id]));
    }

    public function test_customers_with_orders_cannot_be_deleted_and_show_their_orders(): void
    {
        $s = $this->scenario();
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['lines' => [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]]]));
        $order = SalesOrder::query()->firstOrFail();

        $this->get(route('customers.show', $s['customer']))->assertOk()
            ->assertSee($order->number)
            ->assertSee(__('body.customer_has_links'));

        $this->withConfirmedPassword()->delete(route('customers.destroy', $s['customer']))->assertSessionHas('error');
        $this->assertNotNull($s['customer']->fresh());
    }

    public function test_orders_are_numbered_and_isolated_per_business(): void
    {
        $s = $this->scenario();
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['lines' => [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]]]));
        $mine = SalesOrder::query()->firstOrFail();

        $this->flushSession();
        $other = $this->actingAsStaff();
        $theirs = $this->scenario(0);
        $this->post(route('salesorders.orders.store'), $this->payload($theirs, ['lines' => [['variant' => $theirs['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]]]));

        $this->assertSame($mine->number, SalesOrder::query()->where('business_id', $theirs['business']->id)->value('number'), 'each business counts from 1');
        $this->get(route('salesorders.orders.show', $mine))->assertNotFound();
        $this->post(route('salesorders.orders.store'), $this->payload($theirs, ['customer' => $s['customer']->uuid]))->assertStatus(422);
    }

    public function test_sidebar_dashboard_and_exports(): void
    {
        $s = $this->scenario();
        $this->post(route('salesorders.orders.store'), $this->payload($s, ['due_date' => null, 'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]]]));

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('salesorders.orders.index'), false)
            ->assertSee(__('salesorders::orders.dashboard_open'));
        $this->get(route('salesorders.orders.create', ['customer' => $s['customer']->uuid]))->assertOk();
        $this->get(route('salesorders.orders.export.pdf'))->assertOk();
        $this->get(route('salesorders.orders.export.excel'))->assertOk();
    }

    public function test_sales_orders_need_inventory(): void
    {
        $modules = app(ModuleManager::class);

        $this->assertSame(['Inventory'], $modules->requires('SalesOrders'));
        $this->assertFalse($modules->toggle('Inventory', false)['status']);
    }
}
