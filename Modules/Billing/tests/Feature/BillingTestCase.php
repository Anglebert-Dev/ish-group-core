<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\Business\Business;
use App\Models\User;
use App\Services\Module\ModuleManager;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Package;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Support\BillingSettings;
use Tests\TestCase;

abstract class BillingTestCase extends TestCase
{
    protected User $root;

    protected function setUp(): void
    {
        parent::setUp();

        $modules = app(ModuleManager::class);
        $modules->install('Frontend');
        $modules->install('Billing');

        // The platform owner (superadmin) always has everything.
        $this->root = $this->makeSuperAdmin(['email' => 'root@example.com']);
        BillingSettings::set(['currency' => 'RWF', 'tax_rate' => '0', 'grace_days' => '7', 'reminder_days' => '7', 'due_days' => '7']);
    }

    protected function package(array $overrides = []): Package
    {
        return Package::query()->create($overrides + [
            'name' => 'Basic',
            'price' => '10000',
            'interval' => 'month',
            'interval_count' => 1,
            'trial_days' => 0,
            'modules' => ['Frontend'],
            'limits' => ['users' => 3],
            'is_active' => true,
        ]);
    }

    protected function coupon(array $overrides = []): Coupon
    {
        return Coupon::query()->create($overrides + [
            'code' => 'SAVE10',
            'type' => 'percent',
            'value' => 10,
            'duration' => 'once',
            'is_active' => true,
        ]);
    }

    protected function pay(Invoice $invoice, ?string $amount = null): void
    {
        app(PaymentService::class)->submit($invoice->fresh(), $this->root, [
            'amount' => $amount ?? $invoice->fresh()->total,
            'method' => 'bank_transfer',
            'reference' => 'TX-'.$invoice->id,
            'paid_on' => now()->toDateString(),
        ], null, approveNow: true);
    }

    protected function openInvoice(Business $business): ?Invoice
    {
        return Invoice::query()->where('business_id', $business->id)->where('status', 'open')->latest('id')->first();
    }
}
