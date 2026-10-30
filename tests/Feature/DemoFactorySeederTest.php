<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFactorySeeder;
use Modules\Billing\Models\Subscription;
use Modules\Inventory\Models\Movement;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Production\Models\WorkOrder;
use Modules\SalesOrders\Models\SalesOrder;
use Tests\TestCase;

/**
 * The demo data testers get on a test server must always seed cleanly.
 */
class DemoFactorySeederTest extends TestCase
{
    public function test_demo_factory_seeds_every_module_and_logins_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoFactorySeeder::class);

        $owner = User::query()->where('email', DemoFactorySeeder::OWNER_EMAIL)->firstOrFail();
        $business = $owner->business;

        $subscription = Subscription::query()->where('business_id', $business->id)->latest('id')->firstOrFail();
        $this->assertSame('active', $subscription->status->value, 'paid and approved');
        $this->assertTrue($business->canUseModule('GarmentPack'));

        $this->assertSame(4, User::query()->where('business_id', $business->id)->count());
        $this->assertGreaterThan(30, Movement::query()->withoutGlobalScopes()->where('business_id', $business->id)->count());
        $this->assertSame(4, WorkOrder::query()->withoutGlobalScopes()->where('business_id', $business->id)->count());
        $this->assertGreaterThan(3, SalesOrder::query()->withoutGlobalScopes()->where('business_id', $business->id)->count());
        $this->assertGreaterThan(3, SalesInvoice::query()->withoutGlobalScopes()->where('business_id', $business->id)->count());

        // Seeding again does nothing.
        $this->seed(DemoFactorySeeder::class);
        $this->assertSame(1, User::query()->where('email', DemoFactorySeeder::OWNER_EMAIL)->count());

        // Every main screen opens for the owner.
        $this->actingAs($owner, 'staff');
        foreach ([
            'dashboard', 'customers.index', 'inventory.items.index', 'inventory.stores.index', 'inventory.movements.index',
            'salesorders.orders.index', 'quotations.quotes.index', 'production.work-orders.index', 'invoicing.invoices.index',
            'delivery.notes.index', 'purchasing.orders.index', 'purchasing.reorder', 'purchasing.suppliers.index',
            'garmentpack.styles.index', 'garmentpack.workers.index', 'garmentpack.cmt.index', 'reports.index', 'billing.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['sales_products', 'sales_customers', 'production', 'stock_value', 'movements', 'purchases'] as $report) {
            $this->get(route('reports.index', ['report' => $report, 'from' => now()->subDays(40)->toDateString()]))->assertOk();
        }
        $this->get(route('production.work-orders.show', WorkOrder::query()->first()))->assertOk();
        $this->get(route('salesorders.orders.show', SalesOrder::query()->first()))->assertOk();
        $this->get(route('invoicing.invoices.show', SalesInvoice::query()->first()))->assertOk();

        // Staff logins see their parts only.
        $this->flushSession();
        $sales = User::query()->where('email', 'sales@kigalisun.test')->firstOrFail();
        $this->actingAs($sales, 'staff');
        $this->get(route('salesorders.orders.index'))->assertOk();
        $this->get(route('purchasing.orders.index'))->assertForbidden();
    }
}
