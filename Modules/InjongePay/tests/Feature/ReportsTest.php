<?php

namespace Modules\InjongePay\Tests\Feature;

use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\Reports;

class ReportsTest extends InjongePayTestCase
{
    private function pay(int $i, string $code, string $amount): void
    {
        app(PaymentIntake::class)->receive(['transactionId' => 'TX'.$i, 'merchantId' => $code, 'amount' => $amount, 'status' => 'SUCCESS', 'debtorName' => 'PAYER '.$i], Payment::VIA_BANK_PULL);
    }

    public function test_totals_are_per_business_and_add_up(): void
    {
        [$other] = $this->makeBusiness(['business_name' => 'Other']);
        $mine = $this->wallet();
        $theirs = $this->wallet($other);
        $this->pay(1, $mine->merchant_code, '10000.00');
        $this->pay(2, $mine->merchant_code, '5000.00');
        $this->pay(3, $theirs->merchant_code, '7000.00');

        $summary = app(Reports::class)->summary($this->business->id, now()->subDay(), now());

        $this->assertSame(2, $summary['payments_count']);
        $this->assertSame(1500000, $summary['payments_gross']);
        $this->assertSame(30000, $summary['payments_fee']);
        $this->assertSame(1470000, $summary['available']);
        $this->assertSame(2200000, app(Reports::class)->summary(null, now()->subDay(), now())['payments_gross']);
    }

    public function test_business_reports_ledger_and_exports(): void
    {
        $wallet = $this->wallet();
        $this->pay(1, $wallet->merchant_code, '10000.00');
        $this->asOwner();

        $this->get(route('injongepay.reports'))->assertOk()->assertSee('10,000');
        $this->get(route('injongepay.reports.export', ['format' => 'excel']))->assertOk();
        $this->get(route('injongepay.reports.export', ['format' => 'pdf']))->assertOk();
        $this->get(route('injongepay.ledger', $wallet->uuid))->assertOk()->assertSee($wallet->merchant_code);
        $this->get(route('injongepay.platform.finance'))->assertForbidden();
    }

    public function test_platform_overview_lists_every_business(): void
    {
        $this->pay(1, $this->wallet()->merchant_code, '10000.00');
        $this->asOperator();

        $this->get(route('injongepay.platform.finance'))->assertOk()->assertSee('Rhenium Group')->assertSee(__('injongepay::injongepay.platform_balanced'));
        $this->get(route('injongepay.platform.finance.export'))->assertOk();
    }

    public function test_the_dashboard_has_an_injonge_pay_section_for_the_business_and_for_inhills(): void
    {
        $wallet = $this->wallet();
        $this->pay(1, $wallet->merchant_code, '10000.00');

        $this->asOwner()->get(route('dashboard'))->assertOk()
            ->assertSee('Injonge Pay')->assertSee('RWF 9,800')->assertSee('PAYER 1')
            ->assertSee(route('injongepay.cashouts.index'), false)
            ->assertDontSee(__('injongepay::injongepay.dash_needs_you'))
            ->assertDontSee(__('body.recent_activity'));

        $this->flushSession();
        $this->asOperator()->get(route('dashboard'))->assertOk()
            ->assertSee(__('injongepay::injongepay.dash_needs_you'))
            ->assertSee(__('injongepay::injongepay.dash_all_clear'))
            ->assertDontSee('PAYER 1');
    }
}
