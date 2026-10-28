<?php

namespace Modules\Quotations\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Enums\SampleStatus;
use Modules\Quotations\Models\Quotation;
use Modules\Quotations\Models\QuotationDeposit;
use Modules\SalesOrders\Models\SalesOrder;

class QuotationsTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('SalesOrders');
        app(ModuleManager::class)->install('Quotations');
    }

    private function scenario(float $stock = 20): array
    {
        $owner = $this->actingAsStaff();
        $customer = new Customer(['type' => 'company', 'name' => 'Hotel', 'company_name' => 'Lake Hotel']);
        $customer->business_id = $owner->business_id;
        $customer->status = CustomerStatus::Active;
        $customer->save();
        [, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);
        if ($stock > 0) {
            app(StockService::class)->receive($medium, $store, $stock);
        }

        return compact('owner', 'customer', 'medium', 'store');
    }

    private function quote(array $s, array $overrides = []): Quotation
    {
        $this->post(route('quotations.quotes.store'), $overrides + [
            'customer' => $s['customer']->uuid, 'store' => $s['store']->uuid, 'quote_date' => now()->toDateString(),
            'follow_up_date' => now()->toDateString(), 'discount' => 2000,
            'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 40, 'unit_price' => 7000, 'description' => 'Embroidered logo']],
        ])->assertSessionHas('success');

        return Quotation::query()->latest('id')->firstOrFail();
    }

    public function test_convert_to_order_copies_customer_products_and_prices(): void
    {
        $s = $this->scenario();
        $quote = $this->quote($s);
        $this->assertSame('QT-'.now()->format('Y').'-00001', $quote->number);
        $this->assertSame('278000.00', $quote->total);

        $this->get(route('quotations.quotes.index', ['follow_up' => 1]))->assertOk()->assertSee($quote->number);
        $this->put(route('quotations.quotes.status', $quote), ['status' => 'accepted'])->assertSessionHas('success');
        $this->post(route('quotations.quotes.convert', $quote))->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();
        $line = $order->lines()->first();
        $this->assertSame($s['customer']->id, $order->customer_id);
        $this->assertSame(40.0, $line->quantity);
        $this->assertSame('7000.00', $line->unit_price);
        $this->assertSame('Embroidered logo', $line->description);
        $this->assertSame('278000.00', $order->total);

        $quote->refresh();
        $this->assertSame(QuoteStatus::Converted, $quote->status);
        $this->assertSame($order->id, $quote->sales_order_id);
        $this->post(route('quotations.quotes.convert', $quote))->assertSessionHas('error');
        $this->get(route('salesorders.orders.show', $order))->assertSee($quote->number);
    }

    public function test_deposits_are_a_list_and_can_be_reversed(): void
    {
        $s = $this->scenario();
        $quote = $this->quote($s);
        $deposit = fn ($amount) => $this->post(route('quotations.quotes.deposits.store', $quote), ['amount' => $amount, 'paid_on' => now()->toDateString(), 'method' => 'mobile_money']);

        $deposit(50000)->assertSessionHas('success');
        $deposit(30000)->assertSessionHas('success');
        $this->assertSame(80000.0, $quote->depositTotal());

        $first = QuotationDeposit::query()->orderBy('id')->firstOrFail();
        $this->withConfirmedPassword()->post(route('quotations.quotes.deposits.reverse', [$quote, $first]), ['reason' => 'Bounced'])->assertSessionHas('success');
        $this->assertSame(30000.0, $quote->depositTotal());
        $this->get(route('quotations.quotes.show', $quote))->assertOk()->assertSee('Bounced', false)->assertSee(__('quotations::quotations.reversed'));
    }

    public function test_sample_goes_out_of_stock_and_comes_back_with_correct_labels(): void
    {
        $s = $this->scenario(10);
        $quote = $this->quote($s, ['is_sample' => 1, 'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 2, 'unit_price' => 0]]]);
        $this->assertSame(SampleStatus::Requested, $quote->sample_status);
        $sample = fn ($status) => $this->put(route('quotations.quotes.sample', $quote), ['sample_status' => $status]);

        $sample('returned')->assertSessionHas('error', __('quotations::quotations.sample_cannot'));
        $sample('sent')->assertSessionHas('success');
        $this->assertSame(8.0, app(StockService::class)->onHand($s['medium']));

        $sample('approved')->assertSessionHas('success');
        $this->get(route('quotations.quotes.show', $quote))->assertSee('Approved')->assertDontSee('Completed');

        $sample('returned')->assertSessionHas('success');
        $this->assertSame(10.0, app(StockService::class)->onHand($s['medium']));
        $sample('sent')->assertSessionHas('error');
    }

    public function test_deposits_become_payments_on_the_orders_invoice(): void
    {
        app(ModuleManager::class)->install('Invoicing');
        $s = $this->scenario(50);
        $quote = $this->quote($s);
        $this->post(route('quotations.quotes.deposits.store', $quote), ['amount' => 100000, 'paid_on' => now()->toDateString(), 'method' => 'bank', 'reference' => 'TX9']);
        $this->post(route('quotations.quotes.convert', $quote));
        $order = SalesOrder::query()->firstOrFail();
        $this->post(route('salesorders.orders.confirm', $order));

        $this->post(route('invoicing.invoices.store'), [
            'customer' => $s['customer']->uuid, 'store' => $s['store']->uuid, 'sales_order' => $order->uuid,
            'invoice_date' => now()->toDateString(), 'discount' => 2000,
            'lines' => [['variant' => $s['medium']->uuid, 'quantity' => 40, 'unit_price' => 7000]],
        ])->assertSessionHas('success');

        $invoice = SalesInvoice::query()->firstOrFail();
        $this->assertSame('100000.00', $invoice->paid);
        $this->assertSame(178000.0, $invoice->balance());
        $this->assertSame($invoice->id, QuotationDeposit::query()->value('applied_invoice_id'));
        $this->assertSame(10.0, app(StockService::class)->onHand($s['medium']));
    }

    public function test_customers_with_quotes_are_kept_and_quotes_are_isolated(): void
    {
        $s = $this->scenario();
        $quote = $this->quote($s);

        $this->withConfirmedPassword()->delete(route('customers.destroy', $s['customer']))->assertSessionHas('error');
        $this->get(route('dashboard'))->assertSee(__('quotations::quotations.dashboard_follow_up'));

        $this->flushSession();
        $this->actingAsStaff();
        $this->get(route('quotations.quotes.show', $quote))->assertNotFound();
    }
}
