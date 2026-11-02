<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Printing\PrintRegistry;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFactorySeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Delivery\Models\DeliveryNote;
use Modules\GarmentPack\Models\CmtJob;
use Modules\GarmentPack\Models\Style;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Invoicing\Models\SalesPayment;
use Modules\Production\Models\WorkOrder;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseReceipt;
use Modules\Purchasing\Models\SupplierPayment;
use Modules\Quotations\Models\Quotation;
use Modules\Quotations\Models\QuotationDeposit;
use Modules\SalesOrders\Models\SalesOrder;
use Tests\TestCase;

/**
 * Every module document prints, in PDF and the print view, from the
 * demo factory's data (docs/branding-print-build-guide.md §5).
 */
class ModulePrintablesTest extends TestCase
{
    private const RECORDS = [
        'sales_invoice' => SalesInvoice::class,
        'sales_payment' => SalesPayment::class,
        'delivery_note' => DeliveryNote::class,
        'quotation' => Quotation::class,
        'quotation_deposit' => QuotationDeposit::class,
        'sales_order' => SalesOrder::class,
        'purchase_order' => PurchaseOrder::class,
        'purchase_receipt' => PurchaseReceipt::class,
        'supplier_payment' => SupplierPayment::class,
        'work_order' => WorkOrder::class,
        'style_costing' => Style::class,
        'cmt_job' => CmtJob::class,
    ];

    public function test_every_registered_document_prints(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoFactorySeeder::class);
        $this->actingAs(User::query()->where('email', DemoFactorySeeder::OWNER_EMAIL)->firstOrFail(), 'staff');

        $types = array_keys(app(PrintRegistry::class)->labels());
        $this->assertEqualsCanonicalizing(array_keys(self::RECORDS), $types, 'every printable has a record here');

        foreach (self::RECORDS as $type => $model) {
            foreach ($model::query()->get() as $record) {
                $html = $this->get(route('print.show', [$type, $record->getRouteKey()]))->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b[a-z]+::[a-z_]+\.[a-z_]+/', $html, "$type has no missing wording");
                $this->assertStringContainsString('Kigali Sun', $html, "$type is branded");
            }

            $this->get(route('print.pdf', [$type, $model::query()->firstOrFail()->getRouteKey()]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
