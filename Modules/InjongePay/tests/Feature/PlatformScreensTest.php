<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\FeeRateChange;
use Modules\InjongePay\Support\PlatformSettings;

class PlatformScreensTest extends InjongePayTestCase
{
    public function test_a_customer_business_admin_cannot_open_the_platform_panel(): void
    {
        $this->asOwner();

        $this->get(route('injongepay.platform.businesses.index'))->assertForbidden();
        $this->get(route('injongepay.platform.settings'))->assertForbidden();
        $this->get(route('injongepay.platform.businesses.edit', $this->business))->assertForbidden();
        $this->get(route('injongepay.overview'))->assertOk()->assertDontSee(route('injongepay.platform.businesses.index'), false);
    }

    public function test_operator_lists_businesses_and_saves_kyc_with_fee_history(): void
    {
        $this->asOperator();

        $this->get(route('injongepay.platform.businesses.index'))->assertOk()->assertSee('Rhenium Group')->assertSee('Fuel station')->assertDontSee('Inhills</td>', false);
        $this->get(route('injongepay.platform.businesses.edit', $this->business))->assertOk()->assertSee('name="tin"', false);

        $this->withConfirmedPassword()->put(route('injongepay.platform.businesses.update', $this->business), [
            'tin' => '100001498', 'country' => 'RW', 'contact_person' => 'Anglebert', 'contact_phone' => '+250788111222',
            'contact_email' => 'owner@example.com', 'fee_percentage' => '1.5',
        ])->assertRedirect(route('injongepay.platform.businesses.edit', $this->business))->assertSessionHasNoErrors();

        $profile = BusinessProfile::query()->withoutGlobalScopes()->where('business_id', $this->business->id)->firstOrFail();
        $this->assertSame('1.5000', (string) $profile->fee_percentage);
        $this->assertNull($profile->missingKyc());
        $this->assertMatchesRegularExpression('/^INJP\d{12}$/', $profile->code);
        $this->assertSame(1, FeeRateChange::query()->where('business_id', $this->business->id)->count());
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('action', 'injongepay_fee_changed')->where('business_id', $this->business->id)->exists());
    }

    public function test_kyc_follows_gtbank_formats(): void
    {
        $this->asOperator()->withConfirmedPassword()->put(route('injongepay.platform.businesses.update', $this->business), [
            'tin' => '12345', 'country' => 'RW', 'contact_person' => 'A', 'contact_phone' => '0788111222', 'contact_email' => 'x@example.com',
        ])->assertSessionHasErrors(['tin', 'contact_phone']);
    }

    public function test_operator_changes_global_fees_and_generates_the_webhook_secret(): void
    {
        $this->asOperator();

        $this->get(route('injongepay.platform.settings'))->assertOk()->assertSee(url('/api/payments/webhook'));

        $this->withConfirmedPassword()->put(route('injongepay.platform.settings.fees'), ['fee_percentage' => '0.25', 'bank_cut_percentage' => '0.025'])->assertSessionHasNoErrors();
        $this->assertSame('0.2500', PlatformSettings::feePercentage());
        $this->assertSame('0.0250', PlatformSettings::bankCutPercentage());
        $this->get(route('injongepay.platform.settings'))->assertSee('value="0.025"', false)->assertSee('step="0.0001"', false);
        $this->withConfirmedPassword()->put(route('injongepay.platform.settings.fees'), ['fee_percentage' => '0.25', 'bank_cut_percentage' => '0.00001'])->assertSessionHasErrors('bank_cut_percentage');
        $this->assertSame(2, FeeRateChange::query()->whereNull('business_id')->count());

        $response = $this->withConfirmedPassword()->post(route('injongepay.platform.settings.secret'));
        $secret = session('injongepay_new_secret');
        $this->assertSame(40, strlen($secret));
        $this->assertSame($secret, PlatformSettings::webhookSecret());
        $this->assertStringNotContainsString($secret, (string) System::getProperty('injongepay_webhook_secret'));
        $response->assertRedirect();
    }

    public function test_business_overview_shows_only_its_own_wallets(): void
    {
        [$other] = $this->makeBusiness(['business_name' => 'Other']);
        $this->wallet($other, ['name' => 'Other till']);
        $this->wallet(overrides: ['name' => 'Rhenium till']);

        $this->asOwner()->get(route('injongepay.overview'))->assertOk()->assertSee('Rhenium till')->assertDontSee('Other till');
    }
}
