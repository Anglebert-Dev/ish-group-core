<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Services\Branding\BrandingService;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Settings → Branding (docs/branding-print-build-guide.md §3).
 */
class BrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_every_tab_opens(): void
    {
        $this->actingAsStaff();

        foreach (['details', 'look', 'stamp', 'payments'] as $tab) {
            $this->get(route('settings.index', ['tab' => $tab]))->assertOk()->assertSee(__('body.brand_tab_'.$tab));
        }
    }

    public function test_business_details_are_saved_and_emptied_ones_forgotten(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'section' => 'details', 'name' => 'Acme', 'legal_name' => 'Acme Trading Ltd', 'tax_id' => '102345678',
            'registration_no' => 'RDB-9', 'address' => "KG 7 Ave\nKigali", 'phone' => '+250 788 000 000', 'email' => 'hi@acme.test', 'website' => 'acme.test',
        ])->assertRedirect(route('settings.index', ['tab' => 'details']));

        $brand = app(BrandingService::class)->brand($owner->business->fresh());
        $this->assertSame('Acme Trading Ltd', $brand->documentName());
        $this->assertSame('102345678', $brand->taxId);
        $this->assertSame('hi@acme.test', $brand->email);
        $this->assertStringContainsString('TIN: 102345678', implode(' | ', $brand->contactLines()));
        $this->assertTrue(ActivityLog::query()->where('description', 'like', '%branding.tax_id%')->exists(), 'audited');

        $this->put(route('settings.update'), ['section' => 'details', 'name' => 'Acme', 'tax_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull(app(BrandingService::class)->brand($owner->business->fresh())->taxId);
    }

    public function test_look_saves_colours_font_and_logos(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'section' => 'look', 'brand_color' => '#0a7a4b', 'secondary_color' => '#222222', 'font' => 'merriweather',
            'logo' => UploadedFile::fake()->image('logo.png'), 'logo_dark' => UploadedFile::fake()->image('logo-dark.png'),
        ])->assertSessionHasNoErrors();

        $business = $owner->business->fresh();
        $brand = app(BrandingService::class)->brand($business);
        $this->assertSame('#0A7A4B', $brand->primaryColor);
        $this->assertSame('#222222', $brand->secondaryColor);
        $this->assertSame('merriweather', $brand->font);
        Storage::disk('public')->assertExists($brand->logoDarkPath);
        $this->assertStringStartsWith('data:image/png;base64,', $brand->logoDataUri(dark: true));

        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => '#0A7A4B', 'remove_logo_dark' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(app(BrandingService::class)->brand($business->fresh())->logoDarkPath);

        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => 'green'])->assertSessionHasErrors('brand_color');
    }

    public function test_stamp_and_signature_are_private(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'section' => 'stamp', 'stamp' => UploadedFile::fake()->image('stamp.png'), 'signature' => UploadedFile::fake()->image('sig.png'),
            'signatory_name' => 'Jane Doe', 'signatory_title' => 'Director',
        ])->assertSessionHasNoErrors();

        $brand = app(BrandingService::class)->brand($owner->business->fresh());
        Storage::disk('local')->assertExists($brand->stampPath);
        Storage::disk('public')->assertMissing($brand->stampPath);
        $this->assertSame('Jane Doe', $brand->signatoryName);
        $this->assertNotNull($brand->signatureDataUri());

        $this->assertStringContainsString('no-store', $this->get(route('settings.image', 'stamp'))->assertOk()->headers->get('Cache-Control'));

        $this->put(route('settings.update'), ['section' => 'stamp', 'remove_signature' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(app(BrandingService::class)->brand($owner->business->fresh())->signaturePath);
        $this->get(route('settings.image', 'signature'))->assertNotFound();
    }

    public function test_someone_without_settings_access_cannot_see_the_stamp(): void
    {
        $owner = $this->makeStaff();
        $member = $this->makeMember($owner->business, ['list users.user']);
        $this->actingAs($member, 'staff');

        $this->get(route('settings.image', 'stamp'))->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();
    }

    public function test_payment_details_keep_only_filled_lines(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'payments', 'methods' => [
            ['type' => 'bank', 'label' => 'Main (RWF)', 'provider' => 'Bank of Kigali', 'number' => '000-123', 'account_name' => 'Acme Ltd', 'swift' => 'BKIGRWRW'],
            ['type' => 'mobile', 'provider' => 'MTN MoMo', 'number' => '0788 000 000', 'account_name' => 'Acme'],
            ['type' => 'bank', 'label' => 'empty line', 'number' => ''],
        ]])->assertSessionHasNoErrors();

        $methods = app(BrandingService::class)->brand($owner->business->fresh())->paymentMethods;
        $this->assertCount(2, $methods);
        $this->assertSame('BKIGRWRW', $methods[0]['swift']);
        $this->assertSame('mobile', $methods[1]['type']);

        $this->put(route('settings.update'), ['section' => 'payments', 'methods' => [['number' => '']]])->assertSessionHasNoErrors();
        $this->assertSame([], app(BrandingService::class)->brand($owner->business->fresh())->paymentMethods);
    }

    public function test_invoicing_issuer_details_are_used_until_branding_has_its_own(): void
    {
        $owner = $this->actingAsStaff();
        app(BusinessSettings::class)->set('invoicing.tax_id', 'OLD-TIN', $owner->business);
        app(BusinessSettings::class)->set('invoicing.address', 'Old address', $owner->business);

        $brand = app(BrandingService::class)->brand($owner->business);
        $this->assertSame('OLD-TIN', $brand->taxId);
        $this->assertSame('Old address', $brand->address);

        app(BusinessSettings::class)->set('branding.tax_id', 'NEW-TIN', $owner->business);
        $this->assertSame('NEW-TIN', app(BrandingService::class)->brand($owner->business)->taxId);
    }

    public function test_short_name_slogan_description_and_place_logos(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'section' => 'details', 'name' => 'Kigali Sun Garments', 'short_name' => 'KSG', 'slogan' => 'Made in Kigali', 'description' => 'School uniforms and workwear.',
        ])->assertSessionHasNoErrors();
        $this->put(route('settings.update'), ['section' => 'details', 'name' => 'X', 'short_name' => str_repeat('a', 31)])->assertSessionHasErrors('short_name');

        $business = $owner->business->fresh();
        $this->assertSame('KSG', app(BrandingService::class)->detail($business, 'short_name'));
        $this->assertSame('Made in Kigali', app(BrandingService::class)->detail($business, 'slogan'));

        // Phone header: the short name until a phone logo is uploaded.
        $this->get(route('dashboard'))->assertOk()->assertSee('>KSG</span>', false);

        $this->put(route('settings.update'), [
            'section' => 'look', 'brand_color' => '#124EB0',
            'logo_login' => UploadedFile::fake()->image('login.png'), 'logo_mobile' => UploadedFile::fake()->image('mark.png'),
        ])->assertSessionHasNoErrors();

        $mobile = app(BrandingService::class)->logoFor($business, 'mobile');
        $this->assertNotNull(app(BrandingService::class)->logoFor($business, 'login'));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $mobile));
        $this->get(route('dashboard'))->assertOk()->assertSee($mobile, false);

        $this->put(route('settings.update'), ['section' => 'look', 'brand_color' => '#124EB0', 'remove_logo_mobile' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(app(BrandingService::class)->logoFor($business->fresh(), 'mobile'));
        $this->assertNotNull(app(BrandingService::class)->logoFor($business->fresh(), 'login'), 'only the one ticked is removed');
    }
}
