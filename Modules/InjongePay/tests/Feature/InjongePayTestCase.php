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

    protected const BANK = 'https://gt.test/api/integrator';

    /** Points the client at a fake GTBank; $routes: "METHOD path" or "path" => response(s). */
    protected function fakeBank(array $routes = []): void
    {
        config([
            'injongepay.gtbank.base_url' => self::BANK,
            'injongepay.gtbank.client_id' => 'participant',
            'injongepay.gtbank.client_secret' => 'secret-value',
            'injongepay.gtbank.participant_name' => 'INHILLS',
        ]);

        \Illuminate\Support\Facades\Http::fake(array_merge(
            [self::BANK.'/auth/token' => \Illuminate\Support\Facades\Http::response(['token_type' => 'Bearer', 'access_token' => 'tok-1', 'expires_in' => 86400])],
            collect($routes)->mapWithKeys(fn ($response, $path) => [self::BANK.$path.'*' => $response])->all(),
        ));
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
