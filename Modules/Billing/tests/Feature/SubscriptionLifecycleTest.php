<?php

namespace Modules\Billing\Tests\Feature;

use App\Support\Entitlements\Quota;
use Modules\Billing\Enums\InvoiceReason;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingSettings;

class SubscriptionLifecycleTest extends BillingTestCase
{
    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    public function test_paid_package_waits_for_payment_then_activates(): void
    {
        [$business] = $this->makeBusiness();
        $package = $this->package();

        $result = $this->service()->subscribe($business, $package);
        $subscription = $result['data'];

        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertFalse($business->fresh()->canUseModule('Frontend'), 'locked until paid');

        $invoice = $this->openInvoice($business);
        $this->assertSame(InvoiceReason::NewSubscription, $invoice->reason);
        $this->assertSame('10000.00', $invoice->total);

        $this->pay($invoice);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_end->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));
    }

    public function test_trial_gives_access_without_payment_once_per_business(): void
    {
        [$business] = $this->makeBusiness();
        $package = $this->package(['trial_days' => 14]);

        $subscription = $this->service()->subscribe($business, $package)['data'];

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertNull($this->openInvoice($business));
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));

        // A second trial for the same business is not given.
        $this->service()->cancelNow($subscription);
        $again = $this->service()->subscribe($business, $package)['data'];
        $this->assertSame(SubscriptionStatus::Pending, $again->status);
    }

    public function test_free_package_is_active_immediately(): void
    {
        [$business] = $this->makeBusiness();

        $subscription = $this->service()->subscribe($business, $this->package(['price' => 0]))['data'];

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($this->openInvoice($business));
    }

    public function test_module_outside_the_package_is_locked(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $this->service()->subscribe($business, $this->package(['price' => 0, 'modules' => []]));

        $this->assertFalse($business->fresh()->canUseModule('Frontend'));

        $this->actingAs($owner, 'staff');
        $this->get(route('frontend.admin.website.edit'))->assertRedirect(route('billing.index'));
        $this->get(route('billing.index'))->assertOk();
    }

    public function test_business_only_sees_the_modules_in_its_package(): void
    {
        app(\App\Services\Module\ModuleManager::class)->install('Inventory');
        [$business, $owner] = $this->makeBusiness();
        $this->service()->subscribe($business, $this->package(['price' => 0, 'modules' => ['Frontend'], 'limits' => ['inventory.stores' => 2]]));

        $this->actingAs($owner, 'staff');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('frontend.admin.website.edit'), false)
            ->assertDontSee(route('inventory.items.index'), false)
            ->assertViewHas('stats', fn ($stats) => $stats->firstWhere('label', __('body.active_modules'))['value'] === 2); // Frontend + Billing, not Inventory

        $this->get(route('billing.index'))->assertOk()
            ->assertSee('Frontend')
            ->assertDontSee('Inventory')
            ->assertDontSee(__('inventory::inventory.quota_stores'));

        $this->get(route('inventory.items.index'))->assertRedirect(route('billing.index'));
    }

    public function test_platform_owner_business_is_never_locked(): void
    {
        $this->assertTrue($this->root->business->canUseModule('Frontend'));
        $this->assertNull(app(Quota::class)->limit($this->root->business, 'users'));
    }

    public function test_user_limit_comes_from_the_package(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $this->service()->subscribe($business, $this->package(['price' => 0, 'limits' => ['users' => 2]]));
        $this->makeMember($business);

        $this->actingAs($owner, 'staff');
        $this->withConfirmedPassword()->post(route('users.store'), [
            'name' => 'Third',
            'email' => 'third@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('billing.index'));

        $this->assertDatabaseMissing('users', ['email' => 'third@example.com']);
    }

    public function test_partial_payments_add_up_and_rejections_do_not_count(): void
    {
        [$business] = $this->makeBusiness();
        $this->service()->subscribe($business, $this->package());
        $invoice = $this->openInvoice($business);

        $this->pay($invoice, '4000');
        $this->assertSame('open', $invoice->fresh()->status->value);

        $pending = app(PaymentService::class)->submit($invoice->fresh(), $this->root, [
            'amount' => '6000', 'method' => 'mobile_money', 'paid_on' => now()->toDateString(),
        ])['data'];
        app(PaymentService::class)->reject($pending, $this->root, 'Wrong reference');
        $this->assertSame('open', $invoice->fresh()->status->value);

        $this->pay($invoice, '6000');
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_renewal_invoice_grace_period_suspension_and_recovery(): void
    {
        [$business] = $this->makeBusiness();
        $subscription = $this->service()->subscribe($business, $this->package())['data'];
        $this->pay($this->openInvoice($business));
        $subscription->refresh();
        $end = $subscription->current_period_end;

        // Reminder window: renewal invoice issued.
        $this->travelTo($end->copy()->subDays(5));
        $this->service()->runLifecycle();
        $renewal = $this->openInvoice($business);
        $this->assertSame(InvoiceReason::Renewal, $renewal->reason);
        $this->assertTrue($renewal->period_start->equalTo($end));

        // Period over, unpaid: past due but still usable (grace).
        $this->travelTo($end->copy()->addDay());
        $this->service()->runLifecycle();
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));

        // Grace over: locked.
        $this->travelTo($end->copy()->addDays(8));
        $this->service()->runLifecycle();
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
        $this->assertFalse($business->fresh()->canUseModule('Frontend'));

        // Paying restarts it from today (locked time is not billed).
        $this->pay($renewal);
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_start->isToday());
        $this->assertTrue($business->fresh()->canUseModule('Frontend'));
    }

    public function test_paying_renewal_early_keeps_the_billing_anchor(): void
    {
        [$business] = $this->makeBusiness();
        $subscription = $this->service()->subscribe($business, $this->package())['data'];
        $this->pay($this->openInvoice($business));
        $end = $subscription->fresh()->current_period_end;

        $this->travelTo($end->copy()->subDays(3));
        $this->service()->runLifecycle();
        $this->pay($this->openInvoice($business));

        $this->assertTrue($subscription->fresh()->current_period_end->equalTo($end->copy()->addMonthNoOverflow()));
    }

    public function test_upgrade_is_prorated_and_applies_once_paid(): void
    {
        [$business] = $this->makeBusiness();
        $basic = $this->package(['name' => 'Basic', 'price' => '10000']);
        $pro = $this->package(['name' => 'Pro', 'price' => '30000']);
        $subscription = $this->service()->subscribe($business, $basic)['data'];
        $this->pay($this->openInvoice($business));

        // Halfway through the month.
        $period = $subscription->fresh();
        $this->travelTo($period->current_period_start->copy()->addSeconds((int) ($period->current_period_start->diffInSeconds($period->current_period_end) / 2)));

        $result = $this->service()->subscribe($business, $pro);
        $invoice = $result['data'];

        $this->assertSame(InvoiceReason::Change, $invoice->reason);
        $this->assertEqualsWithDelta(5000, (float) $invoice->credit, 1, 'about half of Basic credited');
        $this->assertEqualsWithDelta(25000, (float) $invoice->total, 1);
        $this->assertSame($basic->id, $subscription->fresh()->package_id, 'still Basic until paid');

        $this->pay($invoice);
        $this->assertSame($pro->id, $subscription->fresh()->package_id);
        $this->assertSame('Pro', $subscription->fresh()->name());
    }

    public function test_downgrade_waits_for_period_end(): void
    {
        [$business] = $this->makeBusiness();
        $pro = $this->package(['name' => 'Pro', 'price' => '30000']);
        $basic = $this->package(['name' => 'Basic', 'price' => '10000']);
        $subscription = $this->service()->subscribe($business, $pro)['data'];
        $this->pay($this->openInvoice($business));

        $this->service()->subscribe($business, $basic);
        $subscription->refresh();
        $this->assertSame($pro->id, $subscription->package_id);
        $this->assertSame($basic->id, $subscription->scheduled_package_id);

        $this->travelTo($subscription->current_period_end->copy()->subDays(2));
        $this->service()->runLifecycle();
        $renewal = $this->openInvoice($business);
        $this->assertSame('10000.00', $renewal->total, 'renewal billed at the new price');

        $this->pay($renewal);
        $this->assertSame($basic->id, $subscription->fresh()->package_id);
    }

    public function test_cancel_at_period_end(): void
    {
        [$business] = $this->makeBusiness();
        $subscription = $this->service()->subscribe($business, $this->package())['data'];
        $this->pay($this->openInvoice($business));

        $this->service()->cancel($subscription->fresh());
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
        $this->assertTrue($business->fresh()->canUseModule('Frontend'), 'usable until the end');

        $this->travelTo($subscription->fresh()->current_period_end->copy()->addMinute());
        $this->service()->runLifecycle();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->fresh()->status);
        $this->assertNull($this->openInvoice($business), 'no renewal invoice');
        $this->assertFalse($business->fresh()->canUseModule('Frontend'));
    }

    public function test_coupons_once_and_repeating(): void
    {
        [$business] = $this->makeBusiness();
        $this->coupon(['code' => 'HALF3', 'value' => 50, 'duration' => 'repeating', 'duration_periods' => 2]);
        $subscription = $this->service()->subscribe($business, $this->package(), 'half3')['data'];

        $first = $this->openInvoice($business);
        $this->assertSame('5000.00', $first->total);
        $this->pay($first);

        $this->travelTo($subscription->fresh()->current_period_end->copy()->subDay());
        $this->service()->runLifecycle();
        $second = $this->openInvoice($business);
        $this->assertSame('5000.00', $second->total, 'second period still discounted');
        $this->pay($second);

        $this->travelTo($subscription->fresh()->current_period_end->copy()->subDay());
        $this->service()->runLifecycle();
        $this->assertSame('10000.00', $this->openInvoice($business)->total, 'discount used up');

        // Same business cannot reuse the code.
        [$other] = $this->makeBusiness();
        $this->assertTrue($this->service()->subscribe($other, $this->package(['name' => 'B2']), 'HALF3')['status']);
        $this->assertFalse($this->service()->subscribe($business, $this->package(['name' => 'B3']), 'HALF3')['status']);
    }

    public function test_tax_exclusive_and_inclusive(): void
    {
        [$a] = $this->makeBusiness();
        [$b] = $this->makeBusiness();

        BillingSettings::set(['tax_rate' => '18', 'tax_inclusive' => '0']);
        $this->service()->subscribe($a, $this->package());
        $exclusive = $this->openInvoice($a);
        $this->assertSame('1800.00', $exclusive->tax);
        $this->assertSame('11800.00', $exclusive->total);

        BillingSettings::set(['tax_inclusive' => '1']);
        $this->service()->subscribe($b, $this->package(['name' => 'Incl']));
        $inclusive = $this->openInvoice($b);
        $this->assertSame('10000.00', $inclusive->total);
        $this->assertSame('1525.42', $inclusive->tax);
    }

    public function test_private_package_only_assignable_by_superadmin(): void
    {
        [$business] = $this->makeBusiness();
        $private = $this->package(['is_private' => true]);

        $this->assertFalse($this->service()->subscribe($business, $private)['status']);
        $this->assertTrue($this->service()->subscribe($business, $private, null, true)['status']);
    }

    public function test_package_alone_decides_modules_when_billing_is_installed(): void
    {
        [$business] = $this->makeBusiness();
        $business->forceFill(['enabled_modules' => []])->save(); // stale switch: everything off
        $this->service()->subscribe($business, $this->package(['price' => 0, 'modules' => ['Frontend']]));

        $this->assertTrue($business->fresh()->canUseModule('Frontend'), 'package wins over old switches');

        $this->actingAs($this->root, 'staff');
        $this->get(route('superadmin.businesses.show', $business))
            ->assertOk()
            ->assertSee(__('body.business_modules_from_package'))
            ->assertDontSee('name="modules[]"', false);
        $this->put(route('superadmin.businesses.modules', $business), ['modules' => []])->assertSessionHas('error');
    }

    private function packagePayload(\Modules\Billing\Models\Package $package, array $overrides = []): array
    {
        return $overrides + [
            'name' => $package->name, 'price' => $package->price, 'interval' => 'month', 'interval_count' => 1, 'trial_days' => 0,
            'modules' => $package->modules, 'limits' => $package->limits ?? [], 'is_active' => 1,
        ];
    }

    public function test_package_edits_reach_current_subscribers_only_when_asked(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $package = $this->package(['modules' => ['Frontend'], 'limits' => ['users' => 3]]);
        $this->service()->subscribe($business, $package);
        $this->pay($this->openInvoice($business));
        $subscription = $this->service()->current($business);
        $this->assertFalse($business->fresh()->canUseModule('Inventory'));

        $this->actingAs($this->root, 'staff');
        $this->get(route('superadmin.billing.packages.edit', $package))->assertOk()->assertSee(__('billing::billing.apply_title', ['count' => 1]));

        // Default: only new subscribers.
        $this->put(route('superadmin.billing.packages.update', $package), $this->packagePayload($package, ['modules' => ['Frontend', 'Inventory']]))->assertRedirect();
        app(\Modules\Billing\Support\BillingEntitlements::class)->forget();
        $this->assertFalse($business->fresh()->canUseModule('Inventory'));

        // Modules and limits now, price kept.
        $this->put(route('superadmin.billing.packages.update', $package), $this->packagePayload($package->fresh(), ['price' => 20000, 'limits' => ['users' => 10], 'apply_to_subscribers' => 'terms']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Applied to 1 current businesses.'));
        $subscription->refresh();
        app(\Modules\Billing\Support\BillingEntitlements::class)->forget();
        $this->assertTrue($business->fresh()->canUseModule('Inventory'));
        $this->assertSame(10, $subscription->limit('users'));
        $this->assertSame('10000.00', $subscription->price(), 'price kept');

        // Everything, including the price for the next invoice.
        $this->put(route('superadmin.billing.packages.update', $package), $this->packagePayload($package->fresh(), ['price' => 20000, 'apply_to_subscribers' => 'all']));
        $this->assertSame('20000.00', $subscription->fresh()->price());
        $this->assertTrue(\App\Models\Notification\Notification::query()->where('user_id', $owner->id)->where('title', 'like', '%was updated%')->exists(), 'the business is told');
    }
}
