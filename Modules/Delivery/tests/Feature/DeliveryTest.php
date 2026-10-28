<?php

namespace Modules\Delivery\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use Modules\Delivery\Models\DeliveryNote;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;

class DeliveryTest extends InventoryTestCase
{
    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('SalesOrders');
        app(ModuleManager::class)->install('Delivery');
    }

    private function confirmedOrder(float $stock = 100, float $ordered = 60): array
    {
        $owner = $this->actingAsStaff();
        $customer = new Customer(['type' => 'company', 'name' => 'School', 'company_name' => 'Hill School', 'contact_person' => 'Bursar']);
        $customer->business_id = $owner->business_id;
        $customer->status = CustomerStatus::Active;
        $customer->save();
        [, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);
        app(StockService::class)->receive($medium, $store, $stock);

        $this->post(route('salesorders.orders.store'), [
            'customer' => $customer->uuid, 'store' => $store->uuid, 'order_date' => now()->toDateString(),
            'lines' => [['variant' => $medium->uuid, 'quantity' => $ordered, 'unit_price' => 5000]],
        ]);
        $order = SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        return [$order->fresh('lines'), $medium];
    }

    private function deliver(SalesOrder $order, float $quantity)
    {
        return $this->post(route('delivery.notes.store', $order), [
            'quantities' => [$order->lines->first()->id => $quantity],
            'delivered_on' => now()->toDateString(),
            'receiver_name' => 'Bursar',
            'signature' => self::SIGNATURE,
        ]);
    }

    public function test_an_order_is_delivered_in_parts(): void
    {
        [$order, $medium] = $this->confirmedOrder();

        $this->get(route('delivery.notes.create', $order))->assertOk()->assertSee('Bursar');
        $this->deliver($order, 25)->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::PartlyDelivered, $order->status);
        $this->assertSame(75.0, app(StockService::class)->onHand($medium));
        $this->assertSame(35.0, app(StockService::class)->reserved([$medium->id])[$medium->id], 'only the rest stays reserved');

        $this->deliver($order, 36)->assertSessionHas('error', 'Only 35 of School shirt (M) is left to deliver.');
        $this->deliver($order, 35)->assertSessionHas('success');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(40.0, app(StockService::class)->onHand($medium));
        $this->assertSame(['DN-'.now()->format('Y').'-00001', 'DN-'.now()->format('Y').'-00002'], DeliveryNote::query()->orderBy('id')->pluck('number')->all());

        $note = DeliveryNote::query()->firstOrFail();
        $this->get(route('delivery.notes.show', $note))->assertOk()->assertSee('data:image/png;base64', false);
        $this->get(route('delivery.notes.pdf', $note))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('salesorders.orders.show', $order))->assertSee($note->number);
        $this->get(route('delivery.notes.index'))->assertOk()->assertSee($note->number);
    }

    public function test_part_delivered_orders_cannot_be_cancelled(): void
    {
        [$order] = $this->confirmedOrder();
        $this->deliver($order, 10);

        $this->withConfirmedPassword()->post(route('salesorders.orders.cancel', $order), ['reason' => 'x'])
            ->assertSessionHas('error', __('salesorders::orders.cannot_cancel_partly'));
    }

    public function test_delivery_needs_stock_and_a_receiver(): void
    {
        [$order, $medium] = $this->confirmedOrder(5, 20);

        $this->post(route('delivery.notes.store', $order), ['quantities' => [$order->lines->first()->id => 1], 'delivered_on' => now()->toDateString()])
            ->assertSessionHasErrors('receiver_name');
        $this->deliver($order, 6)->assertSessionHas('error', 'Not enough School shirt (M) in Main store: 5 available, 6 needed.');

        $this->assertSame(0, DeliveryNote::query()->count(), 'nothing saved');
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_invoicing_a_part_delivered_order_delivers_the_rest_once(): void
    {
        app(ModuleManager::class)->install('Invoicing');
        [$order, $medium] = $this->confirmedOrder();
        $this->deliver($order, 20);

        $this->post(route('invoicing.invoices.store'), [
            'customer' => $order->customer->uuid, 'store' => $order->store->uuid, 'sales_order' => $order->uuid,
            'invoice_date' => now()->toDateString(), 'lines' => [['variant' => $medium->uuid, 'quantity' => 60, 'unit_price' => 5000]],
        ])->assertSessionHas('success');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(40.0, app(StockService::class)->onHand($medium), '60 left in total, not 120');
    }
}
