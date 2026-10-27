<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\BusinessSetting;
use App\Support\Documents\DocumentNumber;
use App\Support\Settings\BusinessSettings;
use Tests\TestCase;

class BusinessSettingsAndNumbersTest extends TestCase
{
    public function test_settings_are_stored_per_business_and_audited(): void
    {
        [$alpha] = $this->makeBusiness();
        [$bravo] = $this->makeBusiness();
        $settings = app(BusinessSettings::class);

        $this->assertSame(['pcs'], $settings->get('inventory.units', ['pcs'], $alpha), 'default from code');

        $settings->set('inventory.units', ['pcs', 'm', 'kg'], $alpha);
        $settings->set('inventory.default_store', 3, $alpha);

        $this->assertSame(['pcs', 'm', 'kg'], $settings->get('inventory.units', null, $alpha));
        $this->assertNull($settings->get('inventory.units', null, $bravo), 'other business untouched');
        $this->assertSame(['inventory.units', 'inventory.default_store'], array_keys($settings->group('inventory', $alpha)));

        // A fresh request reads it back from the database.
        $this->app->forgetScopedInstances();
        $this->assertSame(['pcs', 'm', 'kg'], app(BusinessSettings::class)->get('inventory.units', null, $alpha));

        $this->assertTrue(ActivityLog::query()->where('business_id', $alpha->id)->where('description', 'Setting inventory.units changed')->exists());

        app(BusinessSettings::class)->forget('inventory.units', $alpha);
        $this->assertSame(['pcs'], app(BusinessSettings::class)->get('inventory.units', ['pcs'], $alpha));
    }

    public function test_numbers_count_up_per_business_and_type(): void
    {
        [$alpha] = $this->makeBusiness();
        [$bravo] = $this->makeBusiness();
        $numbers = app(DocumentNumber::class);
        $year = now()->format('Y');

        $this->assertSame("SO-{$year}-00001", $numbers->next('sales_order', 'SO', $alpha));
        $this->assertSame("SO-{$year}-00002", $numbers->next('sales_order', 'SO', $alpha));
        $this->assertSame("SO-{$year}-00001", $numbers->next('sales_order', 'SO', $bravo), 'each business has its own sequence');
        $this->assertSame("INV-{$year}-00001", $numbers->next('invoice', 'INV', $alpha), 'each type has its own sequence');
    }

    public function test_yearly_numbers_restart_each_year(): void
    {
        [$business] = $this->makeBusiness();
        $numbers = app(DocumentNumber::class);

        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->assertSame('SO-2026-00001', $numbers->next('sales_order', 'SO', $business));

        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('SO-2027-00001', $numbers->next('sales_order', 'SO', $business));
    }

    public function test_business_can_change_the_number_format(): void
    {
        [$business] = $this->makeBusiness();
        $settings = app(BusinessSettings::class);
        $numbers = app(DocumentNumber::class);

        $settings->set('numbering.invoice.prefix', 'KS', $business);
        $settings->set('numbering.invoice.yearly', false, $business);
        $settings->set('numbering.invoice.digits', 4, $business);

        $this->assertSame('KS-0001', $numbers->next('invoice', 'INV', $business));
        $this->assertSame('KS-0002', $numbers->next('invoice', 'INV', $business));
    }

    public function test_the_current_business_is_used_by_default(): void
    {
        $owner = $this->actingAsStaff();
        $this->get(route('dashboard'))->assertOk();

        app(BusinessSettings::class)->set('numbering.quote.prefix', 'Q');

        $this->assertSame(1, BusinessSetting::query()->where('business_id', $owner->business_id)->count());
        $this->assertStringStartsWith('Q-', app(DocumentNumber::class)->next('quote', 'QT'));
    }
}
