<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Services\Module\ModuleManager;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Purchasing\Enums\PurchaseStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\Supplier;
use Modules\Purchasing\Models\SupplierPayment;

class PurchasingTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Purchasing');
    }

    private function scenario(): array
    {
        $owner = $this->actingAsStaff();
        $this->post(route('purchasing.suppliers.store'), ['name' => 'Kigali Textiles', 'phone' => '0788']);
        $supplier = Supplier::query()->firstOrFail();
        [, [$fabric]] = $this->item($owner->business, [], ['type' => 'material', 'name' => 'Cotton', 'unit' => 'm', 'reorder_level' => 50]);
        $store = $this->defaultStore($owner->business);

        return compact('owner', 'supplier', 'fabric', 'store');
    }

    private function order(array $s, float $quantity = 100, float $cost = 2500): PurchaseOrder
    {
        $this->post(route('purchasing.orders.store'), [
            'supplier' => $s['supplier']->uuid, 'store' => $s['store']->uuid, 'order_date' => now()->toDateString(),
            'lines' => [['variant' => $s['fabric']->uuid, 'quantity' => $quantity, 'unit_price' => $cost]],
        ])->assertSessionHas('success');

        return PurchaseOrder::query()->latest('id')->firstOrFail();
    }

    private function receive(PurchaseOrder $order, float $quantity)
    {
        return $this->post(route('purchasing.orders.receive', $order), [
            'quantities' => [$order->lines()->first()->id => $quantity], 'received_on' => now()->toDateString(), 'supplier_reference' => 'DN-77',
        ]);
    }

    public function test_purchase_order_received_in_parts_puts_stock_in_at_cost(): void
    {
        $s = $this->scenario();
        $order = $this->order($s);
        $this->assertSame('PO-'.now()->format('Y').'-00001', $order->number);
        $this->assertSame('250000.00', $order->total);

        $this->receive($order, 10)->assertSessionHas('error', __('purchasing::purchasing.cannot_receive'));
        $this->post(route('purchasing.orders.send', $order))->assertSessionHas('success');
        $this->put(route('purchasing.orders.update', $order), [
            'supplier' => $s['supplier']->uuid, 'store' => $s['store']->uuid, 'order_date' => now()->toDateString(),
            'lines' => [['variant' => $s['fabric']->uuid, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertSessionHas('error', __('purchasing::purchasing.order_locked'));

        $this->receive($order, 60)->assertSessionHas('success');
        $this->assertSame(PurchaseStatus::PartlyReceived, $order->fresh()->status);
        $this->receive($order, 41)->assertSessionHas('error', 'Only 40 of Cotton is left on this order.');
        $this->receive($order, 40)->assertSessionHas('success');

        $this->assertSame(PurchaseStatus::Received, $order->fresh()->status);
        $this->assertSame(100.0, app(StockService::class)->onHand($s['fabric']));
        $this->assertSame(['2500.00', '2500.00'], Movement::query()->where('type', 'purchase')->pluck('unit_cost')->all());

        $this->get(route('purchasing.orders.show', $order))->assertOk()->assertSee('GRN-'.now()->format('Y').'-00001');
        $this->withConfirmedPassword()->post(route('purchasing.orders.cancel', $order), ['reason' => 'x'])->assertSessionHas('error');
    }

    public function test_supplier_balance_and_payments_are_capped(): void
    {
        $s = $this->scenario();
        $order = $this->order($s, 10, 1000);
        $this->post(route('purchasing.orders.send', $order));
        $this->receive($order, 10);

        $this->get(route('purchasing.suppliers.show', $s['supplier']))->assertOk()->assertSee('RWF 10,000');

        $pay = fn ($amount) => $this->post(route('purchasing.suppliers.pay', $s['supplier']), ['amount' => $amount, 'paid_on' => now()->toDateString(), 'method' => 'bank']);
        $pay(4000)->assertSessionHas('success');
        $pay(6001)->assertSessionHas('error', 'This is more than you owe (RWF 6,000).');

        $payment = SupplierPayment::query()->firstOrFail();
        $this->withConfirmedPassword()->post(route('purchasing.suppliers.payments.reverse', [$s['supplier'], $payment]), ['reason' => 'Duplicate'])->assertSessionHas('success');
        $pay(10000)->assertSessionHas('success');
    }

    public function test_reorder_list_counts_stock_on_order(): void
    {
        $s = $this->scenario();
        app(StockService::class)->receive($s['fabric'], $s['store'], 20);

        $this->get(route('purchasing.reorder'))->assertOk()->assertSee('Cotton')->assertSee('value="80"', false);
        $this->get(route('purchasing.orders.create', ['variants' => [$s['fabric']->uuid => 80]]))->assertOk()->assertSee('Cotton');

        $this->order($s, 40);
        $this->get(route('purchasing.reorder'))->assertDontSee('Cotton');
        $this->get(route('dashboard'))->assertSee(__('purchasing::purchasing.dashboard_reorder'));
    }

    public function test_purchasing_is_isolated_per_business(): void
    {
        $s = $this->scenario();
        $order = $this->order($s);

        $this->flushSession();
        $this->actingAsStaff();
        $this->get(route('purchasing.orders.show', $order))->assertNotFound();
        $this->get(route('purchasing.suppliers.show', $s['supplier']))->assertNotFound();
    }
}
