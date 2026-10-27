<?php

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Enums\ExtraType;
use App\Enums\NormalBalance;
use App\Enums\VehicleColor;
use App\Enums\VehicleOwnershipType;
use App\Libraries\BrandLibrary;
use App\Libraries\ColorLibrary;
use App\Libraries\GeneralLibrary;
use App\Libraries\RwandaPlateLibrary;
use App\Libraries\VehicleCatalogLibrary;
use App\Models\Setting\Setting;
use PHPUnit\Framework\TestCase;

class EnumsAndLibrariesTest extends TestCase
{
    public function test_account_type_normal_balances(): void
    {
        $this->assertSame(NormalBalance::Debit, AccountType::Asset->normalBalance());
        $this->assertSame(NormalBalance::Debit, AccountType::Expense->normalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Revenue->normalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Liability->normalBalance());
        $this->assertSame(NormalBalance::Credit, AccountType::Equity->normalBalance());
    }

    public function test_extra_type_labels(): void
    {
        $this->assertSame('optional', ExtraType::Optional->value);
        $this->assertSame('chauffeur', ExtraType::Chauffeur->value);
        $this->assertSame('Optional add-on', ExtraType::Optional->label());
        $this->assertSame('Chauffeur service', ExtraType::Chauffeur->label());
    }

    public function test_color_library_darkens_and_lightens(): void
    {
        $dark = ColorLibrary::darken('#124EB0');
        $light = ColorLibrary::lighten('#124EB0');
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $dark);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $light);
        $this->assertNotSame('#124EB0', $dark);
        $this->assertNotSame('#124EB0', $light);
    }

    public function test_brand_library_resolves_known_makes(): void
    {
        $this->assertSame('toyota', BrandLibrary::logoSlug('Toyota'));
        $this->assertSame('mercedes', BrandLibrary::logoSlug('Mercedes-Benz'));
        $this->assertSame('volkswagen', BrandLibrary::logoSlug('VW'));
        $this->assertNull(BrandLibrary::logoSlug('Unknown Make'));
        $this->assertContains('Toyota', BrandLibrary::all());
    }

    public function test_vehicle_catalog_lists_makes_and_models(): void
    {
        $this->assertContains('Toyota', VehicleCatalogLibrary::makes());
        $this->assertContains('RAV4', VehicleCatalogLibrary::modelsFor('Toyota'));
        $this->assertContains('CR-V', VehicleCatalogLibrary::modelsFor('Honda'));
        $this->assertSame([], VehicleCatalogLibrary::modelsFor('Unknown'));
        $this->assertContains((int) date('Y'), VehicleCatalogLibrary::years());
        $this->assertTrue(VehicleCatalogLibrary::isValidMake('Toyota'));
        $this->assertTrue(VehicleCatalogLibrary::isValidModel('Toyota', 'Hilux'));
        $this->assertFalse(VehicleCatalogLibrary::isValidModel('Toyota', 'NotARealModel'));
        $this->assertContains('BYD', VehicleCatalogLibrary::makes());
        $this->assertContains('Ioniq 5', VehicleCatalogLibrary::modelsFor('Hyundai'));
        $this->assertContains('Model Y', VehicleCatalogLibrary::modelsFor('Tesla'));
        $this->assertContains('Atto 3', VehicleCatalogLibrary::modelsFor('BYD'));
        $this->assertSame('SUV', VehicleCatalogLibrary::rentalCategoryFor('Toyota', 'RAV4'));
        $this->assertSame('Sedan', VehicleCatalogLibrary::rentalCategoryFor('Toyota', 'Camry'));
        $this->assertSame('Sedan', VehicleCatalogLibrary::rentalCategoryFor('Toyota', 'Corolla'));
        $this->assertSame('Economy', VehicleCatalogLibrary::rentalCategoryFor('Suzuki', 'Swift'));
        $this->assertSame('Pickup', VehicleCatalogLibrary::rentalCategoryFor('Toyota', 'Hilux'));
        $this->assertSame('Van', VehicleCatalogLibrary::rentalCategoryFor('Toyota', 'Hiace'));
        $this->assertSame('Pickup', VehicleCatalogLibrary::rentalCategoryFor('Ford', 'Ranger'));
        $this->assertSame('SUV', VehicleCatalogLibrary::rentalCategoryFor('Land Rover', 'Range Rover'));
        $this->assertSame('SUV', VehicleCatalogLibrary::rentalCategoryFor('Honda', 'CR-V'));
        $this->assertNull(VehicleCatalogLibrary::rentalCategoryFor('Toyota', ''));
        $this->assertSame([
            'vehicle_type' => 'suv',
            'seats' => 7,
            'transmission' => 'automatic',
            'fuel_type' => 'petrol',
            'required_license_category' => 'B',
        ], VehicleCatalogLibrary::suggestedSpecsFor('Kia', 'Sorento'));
        $this->assertSame([
            'vehicle_type' => 'crossover',
            'seats' => 5,
            'transmission' => 'automatic',
            'fuel_type' => 'petrol',
            'required_license_category' => 'B',
        ], VehicleCatalogLibrary::suggestedSpecsFor('Toyota', 'RAV4'));
        $this->assertSame('hatchback', VehicleCatalogLibrary::suggestedSpecsFor('Suzuki', 'Swift')['vehicle_type']);
        $this->assertSame('manual', VehicleCatalogLibrary::suggestedSpecsFor('Suzuki', 'Swift')['transmission']);
        $this->assertSame('pickup', VehicleCatalogLibrary::suggestedSpecsFor('Toyota', 'Hilux')['vehicle_type']);
        $this->assertSame('diesel', VehicleCatalogLibrary::suggestedSpecsFor('Toyota', 'Hilux')['fuel_type']);
        $this->assertSame(14, VehicleCatalogLibrary::suggestedSpecsFor('Toyota', 'Hiace')['seats']);
        $this->assertSame('D1', VehicleCatalogLibrary::suggestedSpecsFor('Toyota', 'Hiace')['required_license_category']);
        $this->assertSame('Electric (EV)', \App\Enums\FuelType::Electric->label());
    }

    public function test_vehicle_color_options_include_legacy_values(): void
    {
        $this->assertContains('White', VehicleColor::options());
        $this->assertContains('Grey', VehicleColor::options());
        $this->assertSame('Pearl', VehicleColor::Pearl->label());
        $this->assertContains('Custom Teal', VehicleColor::options('Custom Teal'));
        $this->assertNotContains('Custom Teal', VehicleColor::options());
    }

    public function test_rwanda_plate_library_normalizes_civilian_plates(): void
    {
        $this->assertSame('RAD 123 A', RwandaPlateLibrary::normalize('rad123a'));
        $this->assertSame('RAD 123 A', RwandaPlateLibrary::normalize('RAD-123-A'));
        $this->assertSame('RAD 123 A', RwandaPlateLibrary::normalize('RAD 123 A'));
        $this->assertTrue(RwandaPlateLibrary::isValid('RAB001Z'));
        $this->assertFalse(RwandaPlateLibrary::isValid('ABC-123'));
        $this->assertFalse(RwandaPlateLibrary::isValid('RAD12A'));
        $this->assertFalse(RwandaPlateLibrary::isValid('RA1123A'));
        $this->assertFalse(RwandaPlateLibrary::isValid('RAD12BA'));
        $this->assertFalse(RwandaPlateLibrary::isValid('1AD123A'));
        $this->assertFalse(RwandaPlateLibrary::isValid('RAD1231'));
        $this->assertSame('RAD123A', RwandaPlateLibrary::compact('RAD 123 A'));
    }

    public function test_general_library_generates_otp_and_formats_blank_dates(): void
    {
        $otp = GeneralLibrary::generateOtp(6);
        $this->assertSame(6, strlen($otp));
        $this->assertTrue(ctype_digit($otp));
        $this->assertSame('—', GeneralLibrary::formatDateTime(null));
    }

    public function test_setting_fallbacks(): void
    {
        $setting = new Setting;
        $this->assertSame(Setting::DEFAULT_COMPANY_NAME, $setting->companyName());
        $this->assertSame(Setting::DEFAULT_BRAND_COLOR, $setting->brandColor());
        $this->assertSame('/favicon-32.png', $setting->faviconUrl());
    }

    public function test_vehicle_ownership_type_values(): void
    {
        $this->assertSame('company', VehicleOwnershipType::Company->value);
        $this->assertSame('outsider', VehicleOwnershipType::Outsider->value);
    }
}
