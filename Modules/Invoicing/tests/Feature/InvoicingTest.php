<?php

namespace Modules\Invoicing\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Business\Business;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use App\Support\Settings\BusinessSettings;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Invoicing\Enums\InvoiceStatus;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Invoicing\Models\SalesPayment;

class InvoicingTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Invoicing');
    }

    private function customer(Business $business): Customer
    {
        $customer = new Customer(['type' => 'company', 'name' => 'Green Hills', 'company_name' => 'Green Hills School']);
        $customer->business_id = $business->id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        return $customer;
    }

    private function scenario(float $stock = 50): array
    {
        $owner = $this->actingAsStaff();
        $customer = $this->customer($owner->business);
        [$item, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);
        if ($stock > 0) {
            app(StockService::class)->receive($medium, $store, $stock);
        }

        return compact('owner', 'customer', 'item', 'medium', 'store');
    }

    private function invoice(array $s, array $lines, array $overrides = [])
    {
        return $this->post(route('invoicing.invoices.store'), $overrides + [
            'customer' => $s['customer']->uuid,
            'store' => $s['store']->uuid,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => $lines,
        ]);
    }

    public function test_invoice_takes_stock_and_applies_tax(): void
    {
        $s = $this->scenario();
        app(BusinessSettings::class)->set('invoicing.tax_rate', 18, $s['owner']->business);

        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 10, 'unit_price' => 5000]], ['discount' => 5000])
            ->assertRedirect()->assertSessionHas('success');

        $invoice = SalesInvoice::query()->firstOrFail();
        $this->assertSame('INV-'.now()->format('Y').'-00001', $invoice->number);
        $this->assertSame('50000.00', $invoice->subtotal);
        $this->assertSame('8100.00', $invoice->tax);      // 18% of 45,000
        $this->assertSame('53100.00', $invoice->total);
        $this->assertTrue($invoice->stock_issued);
        $this->assertSame(40.0, app(StockService::class)->onHand($s['medium']));

        $this->get(route('invoicing.invoices.show', $invoice))->assertOk()->assertSee('Green Hills School');
        $this->get(route('invoicing.invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('invoicing.invoices.index'))->assertOk()->assertSee($invoice->number);
        $this->get(route('invoicing.invoices.export.excel'))->assertOk();
    }

    public function test_two_lines_of_one_item_cannot_oversell(): void
    {
        $s = $this->scenario(8);

        $this->invoice($s, [
            ['variant' => $s['medium']->uuid, 'quantity' => 5, 'unit_price' => 1],
            ['variant' => $s['medium']->uuid, 'quantity' => 5, 'unit_price' => 1],
        ])->assertSessionHas('error', 'Not enough School shirt (M) in Main store: 8 available, 10 needed.');

        $this->assertSame(0, SalesInvoice::query()->count(), 'nothing saved, not even a number');
        $this->assertSame(8.0, app(StockService::class)->onHand($s['medium']));
    }

    public function test_payments_are_a_list_capped_at_the_balance(): void
    {
        $s = $this->scenario();
        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 10, 'unit_price' => 1000]]);
        $invoice = SalesInvoice::query()->firstOrFail();
        $pay = fn ($amount) => $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => $amount, 'paid_on' => now()->toDateString(), 'method' => 'mobile_money', 'reference' => 'MP123']);

        $pay(4000)->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);

        $pay(6001)->assertSessionHas('error', 'This is more than the balance (RWF 6,000).');
        $pay(6000)->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0.0, $invoice->balance());
        $this->assertSame(2, SalesPayment::query()->count());
        $this->assertSame(['RC-'.now()->format('Y').'-00001', 'RC-'.now()->format('Y').'-00002'], SalesPayment::query()->orderBy('id')->pluck('number')->all());

        $pay(1)->assertSessionHas('error');
    }

    public function test_reversed_payment_reopens_the_balance(): void
    {
        $s = $this->scenario();
        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1000]]);
        $invoice = SalesInvoice::query()->firstOrFail();
        $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => 1000, 'paid_on' => now()->toDateString(), 'method' => 'cash']);
        $payment = SalesPayment::query()->firstOrFail();

        $this->withConfirmedPassword()->post(route('invoicing.invoices.payments.reverse', [$invoice, $payment]), ['reason' => 'Wrong invoice'])->assertSessionHas('success');

        $this->assertTrue($payment->fresh()->isReversed());
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertSame(1000.0, $invoice->fresh()->balance());
    }

    public function test_cancel_needs_payments_reversed_and_restores_stock_with_a_credit_note(): void
    {
        $s = $this->scenario(10);
        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 4, 'unit_price' => 1000]]);
        $invoice = SalesInvoice::query()->firstOrFail();
        $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => 500, 'paid_on' => now()->toDateString(), 'method' => 'cash']);

        $this->withConfirmedPassword()->post(route('invoicing.invoices.cancel', $invoice), ['reason' => 'Returned', 'return_stock' => 1])
            ->assertSessionHas('error', __('invoicing::invoicing.cancel_has_payments'));

        $this->post(route('invoicing.invoices.payments.reverse', [$invoice, SalesPayment::query()->first()]), ['reason' => 'Refunded']);
        $this->post(route('invoicing.invoices.cancel', $invoice), ['reason' => 'Returned', 'return_stock' => 1])->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('CN-'.now()->format('Y').'-00001', $invoice->credit_note_number);
        $this->assertSame(10.0, app(StockService::class)->onHand($s['medium']), 'goods back in stock');
        $this->assertSame(1, Movement::query()->where('type', 'sale_return')->count());
        $this->assertSame(0.0, $invoice->balance());
    }

    public function test_customer_shows_balance_and_cannot_be_deleted(): void
    {
        $s = $this->scenario();
        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 3, 'unit_price' => 2500]]);

        $this->get(route('customers.show', $s['customer']))->assertOk()->assertSee('Owes: RWF 7,500');
        $this->withConfirmedPassword()->delete(route('customers.destroy', $s['customer']))->assertSessionHas('error');
    }

    public function test_settings_and_isolation(): void
    {
        $s = $this->scenario();
        $this->put(route('invoicing.settings.update'), ['tax_rate' => 18, 'tax_id' => '101234567', 'footer' => 'MoMo 0788 000 000'])->assertSessionHas('success');
        $this->assertEquals(18, app(BusinessSettings::class)->get('invoicing.tax_rate', null, $s['owner']->business));

        $this->invoice($s, [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]]);
        $invoice = SalesInvoice::query()->firstOrFail();

        $this->flushSession();
        $this->actingAsStaff();
        $this->get(route('invoicing.invoices.show', $invoice))->assertNotFound();
    }

    /**
     * The guide's checkpoint: one real factory order, end to end.
     */
    public function test_factory_order_end_to_end(): void
    {
        $modules = app(ModuleManager::class);
        $modules->install('SalesOrders');
        $modules->install('Production');
        $s = $this->scenario(0);
        [, [$fabric]] = $this->item($s['owner']->business, [], ['type' => 'material', 'name' => 'Cotton', 'unit' => 'm']);
        app(StockService::class)->receive($fabric, $s['store'], 300);

        // 1. Order 50 shirts.
        $this->post(route('salesorders.orders.store'), [
            'customer' => $s['customer']->uuid, 'store' => $s['store']->uuid, 'order_date' => now()->toDateString(),
            'due_date' => now()->addWeeks(2)->toDateString(),
            'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 50, 'unit_price' => 6000]],
        ]);
        $order = \Modules\SalesOrders\Models\SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        // 2. Make them: work order, materials, stage counts.
        $this->post(route('production.work-orders.store'), ['variant' => $s['medium']->uuid, 'store' => $s['store']->uuid, 'quantity' => 50, 'sales_order' => $order->uuid]);
        $wo = \Modules\Production\Models\WorkOrder::query()->firstOrFail();
        $this->post(route('production.work-orders.materials', $wo), ['store' => $s['store']->uuid, 'materials' => [['variant' => $fabric->uuid, 'quantity' => 75]]]);
        foreach ([0, 1, 2, 3] as $stage) {
            $this->post(route('production.work-orders.record', $wo), ['stage' => $stage, 'quantity' => 50])->assertSessionHas('success');
        }
        $this->assertSame('ready', $order->fresh()->status->value);
        $this->assertSame(50.0, app(StockService::class)->onHand($s['medium']));

        // 3. Invoice the order: it is delivered once, stock leaves once.
        $this->get(route('salesorders.orders.show', $order))->assertSee(__('invoicing::invoicing.order_invoice'));
        $this->post(route('invoicing.invoices.store'), [
            'customer' => $s['customer']->uuid, 'store' => $s['store']->uuid, 'sales_order' => $order->uuid,
            'invoice_date' => now()->toDateString(), 'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 50, 'unit_price' => 6000]],
        ])->assertSessionHas('success');
        $invoice = SalesInvoice::query()->firstOrFail();

        $this->assertSame('delivered', $order->fresh()->status->value);
        $this->assertFalse($invoice->stock_issued);
        $this->assertSame(0.0, app(StockService::class)->onHand($s['medium']));
        $this->assertSame(225.0, app(StockService::class)->onHand($fabric));

        // A second invoice for the same order is refused.
        $this->post(route('invoicing.invoices.store'), [
            'customer' => $s['customer']->uuid, 'store' => $s['store']->uuid, 'sales_order' => $order->uuid,
            'invoice_date' => now()->toDateString(), 'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertSessionHas('error');

        // 4. Paid in two parts.
        $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => 100000, 'paid_on' => now()->toDateString(), 'method' => 'bank']);
        $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => 200001, 'paid_on' => now()->toDateString(), 'method' => 'bank'])->assertSessionHas('error');
        $this->post(route('invoicing.invoices.pay', $invoice), ['amount' => 200000, 'paid_on' => now()->toDateString(), 'method' => 'bank'])->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        // The history explains every quantity.
        $this->assertSame(
            ['receipt', 'production_out', 'production_in', 'sale'],
            Movement::query()->orderBy('id')->pluck('type')->map->value->unique()->values()->all(),
        );
    }
}
