<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Support\Tenancy\CurrentBusiness;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\MerchantCodes;
use Tests\TestCase;

/**
 * An operator (Inhills, the primary business) and one customer business.
 */
abstract class InjongePayTestCase extends TestCase
{
    protected Business $operator;

    protected User $operatorAdmin;

    protected Business $business;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('InjongePay');
        [$this->operator, $this->operatorAdmin] = $this->makeBusiness(['business_name' => 'Inhills']);
        System::addProperty('primary_business_id', $this->operator->id);
        [$this->business, $this->owner] = $this->makeBusiness(['business_name' => 'Rhenium Group', 'business_type' => 'fuel_station']);
    }

    protected function asOperator(): static
    {
        $this->actingAs($this->operatorAdmin->fresh(), 'staff');
        app(CurrentBusiness::class)->set($this->operator);

        return $this;
    }

    protected function asOwner(): static
    {
        $this->actingAs($this->owner->fresh(), 'staff');
        app(CurrentBusiness::class)->set($this->business);

        return $this;
    }

    protected function profile(?Business $business = null, array $overrides = []): BusinessProfile
    {
        $profile = BusinessProfile::for($business ?? $this->business);
        $profile->fill($overrides + [
            'tin' => '100001498', 'contact_person' => 'Owner Name', 'contact_phone' => '+250788111222', 'contact_email' => 'owner@example.com',
        ]);
        $profile->business_id = ($business ?? $this->business)->id;
        $profile->save();

        return $profile;
    }

    protected function wallet(?Business $business = null, array $overrides = []): Wallet
    {
        $wallet = new Wallet($overrides + ['name' => 'Main till', 'is_enabled' => true, 'bank_status' => Wallet::BANK_ACTIVE]);
        $wallet->forceFill([
            'business_id' => ($business ?? $this->business)->id,
            'merchant_code' => app(MerchantCodes::class)->next(),
        ])->save();

        return $wallet->fresh();
    }
}
