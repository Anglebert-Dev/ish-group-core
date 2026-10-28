<?php

namespace Modules\Reports\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Invoicing\Models\SalesInvoice;

class ReportsTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Invoicing', 'Production', 'Reports'] as $module) {
            app(ModuleManager::class)->install($module);
        }
    }

    public function test_sales_production_and_stock_reports(): void
    {
        $owner = $this->actingAsStaff();
        [, [$medium]] = $this->item($owner->business, ['M']);
        $medium->forceFill(['cost_price' => 3000])->save();
        $store = $this->defaultStore($owner->business);
        app(StockService::class)->receive($medium, $store, 50);
        $customer = new Customer(['type' => 'company', 'name' => 'Hill', 'company_name' => 'Hill School']);
        $customer->business_id = $owner->business_id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        $invoice = fn ($qty) => $this->post(route('invoicing.invoices.store'), [
            'customer' => $customer->uuid, 'store' => $store->uuid, 'invoice_date' => now()->toDateString(),
            'lines' => [['variant' => $medium->uuid, 'quantity' => $qty, 'unit_price' => 5000]],
        ]);
        $invoice(10);
        $invoice(4);
        $cancelled = SalesInvoice::query()->latest('id')->first();
        $this->withConfirmedPassword()->post(route('invoicing.invoices.cancel', $cancelled), ['reason' => 'x', 'return_stock' => 1]);

        $this->get(route('reports.index', ['report' => 'sales_products']))->assertOk()
            ->assertSee('School shirt (M)')
            ->assertSee('RWF 50,000')   // revenue: cancelled invoice ignored
            ->assertSee('RWF 20,000');  // margin: 10 × (5000 − 3000)
        $this->get(route('reports.index', ['report' => 'sales_customers']))->assertSee('Hill School');

        $this->post(route('production.work-orders.store'), ['variant' => $medium->uuid, 'store' => $store->uuid, 'quantity' => 10, 'line' => 'Line A']);
        $wo = \Modules\Production\Models\WorkOrder::query()->firstOrFail();
        foreach ([0, 1, 2] as $stage) {
            $this->post(route('production.work-orders.record', $wo), ['stage' => $stage, 'quantity' => 10]);
        }
        $this->post(route('production.work-orders.record', $wo), ['stage' => 3, 'quantity' => 8]);
        $this->post(route('production.work-orders.reject', $wo), ['quantity' => 2, 'reason' => 'Stains']);

        $this->get(route('reports.index', ['report' => 'production']))->assertOk()->assertSee('Line A')->assertSee('20%');

        // 50 − 10 sold + 8 made = 48 × 3000.
        $this->get(route('reports.index', ['report' => 'stock_value']))->assertOk()->assertSee('RWF 144,000');
        $this->get(route('reports.index', ['report' => 'movements']))->assertOk()->assertSee('Sale return');

        $this->get(route('reports.export', ['format' => 'excel', 'report' => 'sales_products']))->assertOk();
        $this->get(route('reports.export', ['format' => 'pdf', 'report' => 'stock_value']))->assertOk();
    }

    public function test_reports_follow_the_package_and_permissions(): void
    {
        [$business] = $this->makeBusiness();
        $business->forceFill(['enabled_modules' => ['Inventory', 'Reports']])->save();

        $this->actingAs($this->makeMember($business, ['list inventory.item']), 'staff');
        $this->get(route('reports.index'))->assertOk()
            ->assertSee(__('reports::reports.stock_value'))
            ->assertDontSee(__('reports::reports.sales_products'))
            ->assertDontSee(__('reports::reports.movements'));
    }
}
