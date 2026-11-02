<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Models\Customer\Customer;
use App\Services\Branding\BrandingService;
use App\Services\Printing\PrintService;
use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use App\Support\Printing\PrintDesign;
use App\Support\Printing\PrintRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The shared print engine (docs/branding-print-build-guide.md §4).
 */
class PrintEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        config(['printing.types' => ['test_customer' => TestCustomerPrintable::class]]);
        app()->forgetInstance(PrintRegistry::class);
    }

    public function test_every_layout_renders_a_pdf(): void
    {
        [$business] = $this->makeBusiness();

        foreach (PrintDesign::LAYOUTS as $layout) {
            $pdf = app(PrintService::class)->pdfBytes($this->invoice(), $business, PrintDesign::for($business, null, ['layout' => $layout]));
            $this->assertStringStartsWith('%PDF', $pdf, $layout);
        }
    }

    public function test_the_document_carries_the_brand_payment_and_signature(): void
    {
        [$business, $owner] = $this->makeBusiness(['business_name' => 'Acme Garments']);
        $this->actingAs($owner, 'staff');
        $branding = app(BrandingService::class);
        $branding->saveDetails($business, ['name' => 'Acme Garments', 'legal_name' => 'Acme Garments Ltd', 'tax_id' => '102030']);
        $branding->savePaymentMethods($business, [['type' => 'mobile', 'provider' => 'MTN MoMo', 'number' => '0788 111 222', 'account_name' => 'Acme']]);
        $branding->saveStampAndSignature($business, ['signatory_name' => 'Jane Doe', 'signatory_title' => 'Director'], UploadedFile::fake()->image('stamp.png'), UploadedFile::fake()->image('sig.png'));
        app(\App\Support\Settings\BusinessSettings::class)->set('print.types', ['test' => ['terms' => 'Pay within 14 days.', 'footer' => 'Invoice footer line']], $business);

        $html = app(PrintService::class)->html($this->invoice(), $business->fresh(), null, autoPrint: false)->render();

        $this->assertStringContainsString('Acme Garments Ltd', $html);
        $this->assertStringContainsString('TIN: 102030', $html);
        $this->assertStringContainsString('0788 111 222', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Pay within 14 days.', $html);
        $this->assertStringContainsString('Invoice footer line', $html);
        $this->assertStringContainsString('UNPAID', $html);
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'), 'stamp and signature embedded');

        $quiet = app(PrintService::class)->html($this->invoice(), $business->fresh(), null, autoPrint: false,
            design: PrintDesign::for($business->fresh(), null, ['show_payment' => false, 'show_watermark' => false, 'show_signature' => false, 'show_stamp' => false]))->render();
        $this->assertStringNotContainsString('0788 111 222', $quiet);
        $this->assertStringNotContainsString('class="watermark"', $quiet);
        $this->assertStringNotContainsString('data:image/png;base64,', $quiet);
    }

    public function test_excel_is_branded(): void
    {
        [$business] = $this->makeBusiness(['business_name' => 'Acme Garments']);
        $business->update(['brand_color' => '#0A7A4B']);

        $response = app(PrintService::class)->excel($this->invoice(), $business->fresh(), 'inv');
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        ob_start();
        $response->sendContent();
        file_put_contents($file, ob_get_clean());

        $sheet = IOFactory::load($file)->getActiveSheet();
        $this->assertSame('Acme Garments', $sheet->getCell('A1')->getValue());
        $values = collect($sheet->toArray())->flatten()->filter()->values();
        $this->assertContains('Polo shirt', $values);
        $this->assertContains('Total', $values);
        $header = collect($sheet->getRowIterator())->first(fn ($row) => $sheet->getCell('A'.$row->getRowIndex())->getValue() === 'Item')->getRowIndex();
        $this->assertSame('0A7A4B', $sheet->getStyle('A'.$header)->getFill()->getStartColor()->getRGB());
        @unlink($file);
    }

    public function test_lists_print_view_pdf_and_excel_go_through_the_engine(): void
    {
        $this->actingAsStaff();

        $this->get(route('users.export.pdf', ['print' => 1]))->assertOk()->assertSee('window.print()', false)->assertSee(__('body.print_download_pdf'));
        $this->get(route('users.export.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('users.export.excel'))->assertOk();
        $this->assertTrue(ActivityLog::query()->where('description', 'like', '%Print%')->exists());
    }

    public function test_print_routes_serve_registered_documents_within_the_business(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $mine = $this->customer($business, 'Mine Person');
        [$other] = $this->makeBusiness();
        $theirs = $this->customer($other, 'Theirs Person');
        $this->actingAs($owner, 'staff');

        $this->get(route('print.show', ['test_customer', $mine->getRouteKey()]))->assertOk()->assertSee('Mine Person');
        $this->get(route('print.pdf', ['test_customer', $mine->getRouteKey()]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('print.show', ['test_customer', $theirs->getRouteKey()]))->assertNotFound();
        $this->get(route('print.show', ['nope', $mine->getRouteKey()]))->assertNotFound();
        $this->assertTrue(ActivityLog::query()->where('description', 'like', 'Printed Customer%')->exists());
    }

    public function test_printing_needs_the_documents_permission(): void
    {
        [$business] = $this->makeBusiness();
        $mine = $this->customer($business, 'Mine Person');
        $this->actingAs($this->makeMember($business, ['list users.user']), 'staff');

        $this->get(route('print.show', ['test_customer', $mine->getRouteKey()]))->assertForbidden();
        $this->get(route('print.pdf', ['test_customer', $mine->getRouteKey()]))->assertForbidden();
    }

    public function test_the_designer_previews_unsaved_choices_and_saves_them(): void
    {
        $owner = $this->actingAsStaff();

        $this->get(route('settings.index', ['tab' => 'documents']))->assertOk()->assertSee(__('body.design_preview'))->assertSee('Customer card');
        $this->get(route('settings.print-preview', ['layout' => 'modern', 'header_note' => 'Unsaved tagline']))->assertOk()->assertSee('Unsaved tagline');
        $this->get(route('settings.print-preview', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->put(route('settings.update'), [
            'section' => 'documents', 'layout' => 'compact', 'paper' => 'letter', 'accent' => '#aa3300', 'header_note' => 'Since 2019',
            'show_contact' => '1', 'show_payment' => '1', 'types' => ['test_customer' => ['terms' => 'Customer terms']],
        ])->assertRedirect(route('settings.index', ['tab' => 'documents']));

        $design = PrintDesign::for($owner->business->fresh());
        $this->assertSame('compact', $design->layout);
        $this->assertSame('letter', $design->paper);
        $this->assertSame('#AA3300', $design->accent);
        $this->assertSame('Since 2019', $design->headerNote);
        $this->assertFalse($design->showWatermark, 'unticked boxes are saved as off');
        $this->assertSame('Customer terms', $design->termsFor('test_customer'));
    }

    private function invoice(): Document
    {
        return Document::make('test', 'Invoice')->number('INV-1')->meta('Date', '02 Nov 2026')
            ->party('Bill to', 'Kigali Sun', ['KG 11 Ave'])
            ->table([['Item'], ['Qty', 'right'], ['Amount', 'right']], [['Polo shirt', '2', '13,000']])
            ->total('Total', 'RWF 13,000', strong: true)
            ->watermark('UNPAID')->withPayment()->withSignature();
    }

    private function customer(Business $business, string $name): Customer
    {
        $customer = new Customer(['type' => 'person', 'name' => $name]);
        $customer->business_id = $business->id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        return $customer;
    }
}

/** A printable for the tests: the core has no documents of its own yet. */
class TestCustomerPrintable extends Printable
{
    public function label(): string
    {
        return 'Customer card';
    }

    public function permission(): string
    {
        return 'read customers.customer';
    }

    public function find(string $key): Model
    {
        return Customer::query()->where('uuid', $key)->firstOrFail();
    }

    public function document(Model $record): Document
    {
        return Document::make('test_customer', 'Customer')->number($record->name)->party('Customer', $record->name);
    }
}
