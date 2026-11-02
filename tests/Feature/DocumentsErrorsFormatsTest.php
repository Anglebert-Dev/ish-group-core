<?php

namespace Tests\Feature;

use App\Models\Business\Business;
use App\Services\Printing\PrintService;
use App\Services\Setting\SettingService;
use App\Support\Printing\Document;
use App\Support\Printing\PrintDesign;
use App\Support\Settings\BusinessSettings;
use Tests\TestCase;

/**
 * Document extras, branded error pages, formats and footer
 * (docs/branding.md §3.11, §3.17, phase B8).
 */
class DocumentsErrorsFormatsTest extends TestCase
{
    public function test_watermark_text_never_replaces_a_status_and_each_type_can_have_its_layout(): void
    {
        [$business] = $this->makeBusiness();
        $settings = app(BusinessSettings::class);
        $settings->set('print.watermark_text', 'COPY', $business);
        $settings->set('print.background', 'tint', $business);
        $settings->set('print.types', ['receipt' => ['layout' => 'compact']], $business);
        $printer = app(PrintService::class);

        $plain = $printer->html(Document::make('quote', 'Quotation')->number('Q-1'), $business, null, autoPrint: false)->render();
        $this->assertStringContainsString('<div class="watermark">COPY</div>', $plain);
        $this->assertStringContainsString('html, body { background: #', $plain);

        $paid = $printer->html(Document::make('quote', 'Invoice')->number('I-1')->watermark('PAID'), $business, null, autoPrint: false)->render();
        $this->assertStringContainsString('<div class="watermark">PAID</div>', $paid);
        $this->assertStringNotContainsString('>COPY<', $paid);

        $this->assertSame('compact', PrintDesign::for($business)->forType('receipt')->layout);
        $this->assertSame('classic', PrintDesign::for($business)->forType('quote')->layout);
    }

    public function test_the_designer_saves_the_new_document_choices(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'documents', 'layout' => 'classic', 'watermark_text' => 'COPY', 'background' => 'tint', 'types' => ['quotation' => ['layout' => 'modern']]])
            ->assertSessionHasNoErrors();

        $design = PrintDesign::for($owner->business->fresh());
        $this->assertSame('COPY', $design->watermarkText);
        $this->assertSame('modern', $design->forType('quotation')->layout);
    }

    public function test_error_pages_wear_the_brand(): void
    {
        $owner = $this->actingAsStaff();
        $owner->business->update(['brand_color' => '#0A7A4B']);

        $this->get('/app/no-such-page')->assertNotFound()->assertSee(__('body.error_404_title'))->assertSee('--brand: #0A7A4B', false)->assertSee($owner->business->name);
    }

    public function test_server_error_pages_render_without_the_database(): void
    {
        $this->app->instance(SettingService::class, new class extends SettingService
        {
            public function __construct() {}

            public function current(): Business
            {
                throw new \RuntimeException('database down');
            }
        });

        foreach ([500, 503] as $code) {
            $html = view('errors.'.$code)->render();
            $this->assertStringContainsString(__('body.error_'.$code.'_title'), $html);
            $this->assertStringContainsString('--c-brand:', $html, 'built-in look');
        }

        $this->expectException(\Throwable::class);
        view('app.dashboard.index')->render();
    }

    public function test_formats_and_footer_follow_the_business(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'section' => 'details', 'name' => $owner->business->name, 'date_format' => 'm/d/Y', 'number_format' => 'dot_comma',
            'support_email' => 'help@kigalisun.test', 'help_url' => 'https://help.kigalisun.test', 'copyright' => '© Kigali Sun Ltd',
        ])->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk();
        $this->assertSame('m/d/Y', short_date_format());
        $this->assertStringEndsWith('1.234,50', money(1234.5));

        $this->get(route('customers.index'))->assertOk()
            ->assertSee('© Kigali Sun Ltd')->assertSee('help@kigalisun.test')
            ->assertSee('href="https://help.kigalisun.test"', false);
    }
}
