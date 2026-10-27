<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\System\System;
use App\Models\User;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Services\SubscriptionService;

class PricingSignupTest extends BillingTestCase
{
    private function register(array $extra = [])
    {
        return $this->post(route('staff.register.store'), $extra + [
            'business_name' => 'New Shop',
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ]);
    }

    public function test_pricing_page_lists_only_public_packages(): void
    {
        $this->package(['name' => 'Public Plan']);
        $this->package(['name' => 'Secret Plan', 'is_private' => true]);
        [$someone] = $this->makeBusiness();
        $this->package(['name' => 'Only For One', 'business_ids' => [$someone->id]]);
        System::addProperty('allow_registration', '1');

        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('Public Plan')
            ->assertDontSee('Secret Plan')
            ->assertDontSee('Only For One')
            ->assertSee(route('staff.register'), false);

        $this->get(route('staff.login'))->assertSee(route('pricing'), false);
    }

    public function test_signing_up_with_a_paid_package_lands_on_its_invoice(): void
    {
        System::addProperty('allow_registration', '1');
        $package = $this->package(['name' => 'Pro']);

        $this->get(route('staff.register', ['package' => $package->uuid]))->assertOk()->assertSee('Pro')->assertSee($package->uuid, false);

        $response = $this->register(['package' => $package->uuid]);

        $business = User::query()->where('email', 'new-owner@example.com')->firstOrFail()->business;
        $invoice = $this->openInvoice($business);
        $response->assertRedirect(route('billing.invoices.show', $invoice));
        $this->assertSame(SubscriptionStatus::Pending, app(SubscriptionService::class)->current($business)->status);
    }

    public function test_signing_up_with_a_trial_package_goes_straight_in(): void
    {
        System::addProperty('allow_registration', '1');
        $package = $this->package(['name' => 'Trial Plan', 'trial_days' => 14]);

        $this->register(['package' => $package->uuid])->assertRedirect(route('dashboard'));

        $business = User::query()->where('email', 'new-owner@example.com')->firstOrFail()->business;
        $this->assertSame(SubscriptionStatus::Trialing, app(SubscriptionService::class)->current($business)->status);
    }

    public function test_private_package_cannot_be_picked_at_sign_up(): void
    {
        System::addProperty('allow_registration', '1');
        $private = $this->package(['is_private' => true]);

        $this->register(['package' => $private->uuid])->assertRedirect(route('dashboard'));

        $business = User::query()->where('email', 'new-owner@example.com')->firstOrFail()->business;
        $this->assertNull(app(SubscriptionService::class)->current($business));
    }
}
