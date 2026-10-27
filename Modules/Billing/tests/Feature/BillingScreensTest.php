<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingSettings;

class BillingScreensTest extends BillingTestCase
{
    public function test_superadmin_billing_pages_render(): void
    {
        $this->actingAs($this->root, 'staff');
        [$business] = $this->makeBusiness();
        $package = $this->package();
        $coupon = $this->coupon();
        $subscription = app(SubscriptionService::class)->subscribe($business, $package)['data'];
        $invoice = $this->openInvoice($business);

        foreach ([
            route('superadmin.billing.overview'),
            route('superadmin.billing.packages.index'),
            route('superadmin.billing.packages.create'),
            route('superadmin.billing.packages.edit', $package),
            route('superadmin.billing.subscriptions.index'),
            route('superadmin.billing.subscriptions.show', $subscription),
            route('superadmin.billing.invoices.index', ['status' => 'overdue']),
            route('superadmin.billing.invoices.show', $invoice),
            route('superadmin.billing.payments.index'),
            route('superadmin.billing.coupons.index'),
            route('superadmin.billing.coupons.create'),
            route('superadmin.billing.coupons.edit', $coupon),
            route('superadmin.billing.settings.index'),
            route('superadmin.businesses.show', $business),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('superadmin.billing.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('dashboard'))->assertSee(route('superadmin.billing.overview'), false);
    }

    public function test_superadmin_creates_package_coupon_and_settings(): void
    {
        $this->actingAs($this->root, 'staff');

        $this->post(route('superadmin.billing.packages.store'), [
            'name' => 'Growth',
            'price' => '45000',
            'interval' => 'month',
            'interval_count' => 3,
            'trial_days' => 14,
            'modules' => ['Frontend'],
            'limits' => ['users' => 10],
            'features' => "Priority support\nCustom domain",
            'is_active' => '1',
            'is_popular' => '1',
            'is_private' => '0',
            'once_per_business' => '0',
        ])->assertRedirect(route('superadmin.billing.packages.index'));

        $package = Package::query()->where('name', 'Growth')->firstOrFail();
        $this->assertSame(['Frontend'], $package->modules);
        $this->assertSame(10, $package->limit('users'));
        $this->assertSame(['Priority support', 'Custom domain'], $package->features);
        $this->assertSame('every 3 months', 'every '.$package->intervalLabel());

        $this->post(route('superadmin.billing.coupons.store'), [
            'code' => 'launch-50', 'type' => 'percent', 'value' => 50, 'duration' => 'repeating',
            'duration_periods' => 3, 'package_ids' => [$package->id], 'is_active' => '1',
        ])->assertRedirect(route('superadmin.billing.coupons.index'));
        $this->assertNotNull(Coupon::query()->where('code', 'LAUNCH-50')->first());

        $this->put(route('superadmin.billing.settings.update'), [
            'currency' => 'usd', 'tax_rate' => 18, 'tax_inclusive' => '1', 'tax_label' => 'VAT',
            'grace_days' => 5, 'reminder_days' => 10, 'due_days' => 3, 'invoice_prefix' => 'ish',
            'default_package_id' => $package->id, 'payment_instructions' => 'MoMo: 0788 000 000',
        ])->assertRedirect(route('superadmin.billing.settings.index'));

        BillingSettings::flush();
        $this->assertSame('USD', BillingSettings::get('currency'));
        $this->assertSame('ISH', BillingSettings::get('invoice_prefix'));

        // Default package: new businesses start on it (with its trial).
        [$newBusiness] = $this->makeBusiness();
        $this->assertSame(SubscriptionStatus::Trialing, app(SubscriptionService::class)->current($newBusiness)->status);
    }

    public function test_business_chooses_plan_pays_with_proof_and_superadmin_approves(): void
    {
        Storage::fake('local');
        [$business, $owner] = $this->makeBusiness();
        $package = $this->package(['name' => 'Starter', 'features' => ['Email support']]);
        $this->coupon(['code' => 'WELCOME', 'value' => 20]);

        $this->actingAs($owner, 'staff');
        $this->get(route('billing.index'))->assertOk()->assertSee(__('billing::billing.no_subscription'));
        $this->get(route('billing.plans'))->assertOk()->assertSee('Starter')->assertSee('Email support');
        $this->get(route('billing.checkout', [$package, 'coupon' => 'welcome']))->assertOk()->assertSee('WELCOME');

        $this->withConfirmedPassword()
            ->post(route('billing.subscribe', $package), ['coupon' => 'WELCOME'])
            ->assertRedirect();

        $invoice = $this->openInvoice($business);
        $this->assertSame('8000.00', $invoice->total);
        $this->get(route('billing.invoices.show', $invoice))->assertOk()->assertSee($invoice->number);
        $this->get(route('billing.invoices.pdf', $invoice))->assertOk();

        $this->post(route('billing.invoices.pay', $invoice), [
            'amount' => '8000', 'method' => 'mobile_money', 'reference' => 'MP123',
            'paid_on' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionHas('success');

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertFalse($business->fresh()->canUseModule('Frontend'), 'not before approval');

        // Superadmin reviews it.
        $this->flushSession();
        $this->actingAs($this->root, 'staff');
        $this->get(route('superadmin.billing.payments.index'))->assertOk()->assertSee('MP123');
        $this->get(route('superadmin.billing.payments.proof', $payment))->assertOk();
        $this->withConfirmedPassword()->post(route('superadmin.billing.payments.approve', $payment))->assertSessionHas('success');

        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));
    }

    public function test_superadmin_records_offline_payment_and_voids_invoices(): void
    {
        [$business] = $this->makeBusiness();
        app(SubscriptionService::class)->subscribe($business, $this->package());
        $invoice = $this->openInvoice($business);

        $this->actingAs($this->root, 'staff');
        $this->withConfirmedPassword()->post(route('superadmin.billing.invoices.record-payment', $invoice), [
            'amount' => $invoice->total, 'method' => 'cash', 'paid_on' => now()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status->value);

        [$other] = $this->makeBusiness();
        app(SubscriptionService::class)->subscribe($other, $this->package(['name' => 'Other']));
        $toVoid = $this->openInvoice($other);
        $this->withConfirmedPassword()->post(route('superadmin.billing.invoices.void', $toVoid), ['reason' => 'Duplicate'])->assertSessionHas('success');
        $this->assertSame('void', $toVoid->fresh()->status->value);
    }

    public function test_superadmin_assigns_package_from_business_page(): void
    {
        [$business] = $this->makeBusiness();
        $package = $this->package(['price' => 0, 'is_private' => true]);

        $this->actingAs($this->root, 'staff');
        $this->withConfirmedPassword()
            ->post(route('superadmin.billing.subscriptions.assign', $business), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->assertSame(SubscriptionStatus::Active, app(SubscriptionService::class)->current($business)->status);
    }

    public function test_business_cannot_see_another_business_invoice_or_reach_superadmin_billing(): void
    {
        [$a, $ownerA] = $this->makeBusiness();
        [$b] = $this->makeBusiness();
        app(SubscriptionService::class)->subscribe($b, $this->package());
        $invoiceB = $this->openInvoice($b);

        $this->actingAs($ownerA, 'staff');
        $this->get(route('billing.invoices.show', $invoiceB))->assertNotFound();
        $this->post(route('billing.invoices.pay', $invoiceB), ['amount' => 1, 'method' => 'cash', 'paid_on' => now()->toDateString()])->assertNotFound();
        $this->get(route('superadmin.billing.overview'))->assertForbidden();
    }

    public function test_billing_menu_shows_for_business_and_locked_module_menu_hides(): void
    {
        [$business, $owner] = $this->makeBusiness();
        app(SubscriptionService::class)->subscribe($business, $this->package(['price' => 0, 'modules' => []]));

        $this->actingAs($owner, 'staff');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('billing.index'), false)
            ->assertDontSee(route('frontend.admin.website.edit'), false);
    }
}
