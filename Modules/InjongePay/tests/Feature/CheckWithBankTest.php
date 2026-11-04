<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\PaymentIntake;

/** Anything sent to GTBank can be asked about by hand; the scheduled jobs are only the safety net. */
class CheckWithBankTest extends InjongePayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function cashout(Wallet $wallet, string $status = Cashout::UNKNOWN): Cashout
    {
        $cashout = new Cashout;
        $cashout->forceFill(['reference' => 'CSH20261104000001', 'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id, 'amount' => 100000, 'account' => '0788811607', 'account_holder' => 'Rose', 'status' => $status, 'submitted_at' => now()])->save();

        return $cashout;
    }

    private function pending(Wallet $wallet): Payment
    {
        $this->fakeBank();

        return app(PaymentIntake::class)->receive(['transactionId' => 'TX-1', 'merchantId' => $wallet->merchant_code, 'amount' => '10000.00', 'valueDate' => '2026-11-02', 'status' => 'SUCCESS'], Payment::VIA_WEBHOOK)[1];
    }

    private function row(Wallet $wallet): array
    {
        return ['transactionId' => 'TX-1', 'merchantId' => $wallet->merchant_code, 'amount' => '10000.00', 'status' => 'SUCCESS', 'valueDate' => '2026-11-02', 'createdAt' => '2026-11-02T09:55:19'];
    }

    public function test_a_business_asks_gtbank_about_its_pending_cashout_and_nothing_is_resent(): void
    {
        $cashout = $this->cashout($this->wallet());
        $this->fakeBank(['/disbursement/status' => Http::response(['status' => 'PDNG', 'request_id' => $cashout->reference])]);
        $this->asOwner();

        $this->get(route('injongepay.cashouts.show', $cashout))->assertSee(__('injongepay::injongepay.check_now'));
        $this->post(route('injongepay.cashouts.check', $cashout))->assertRedirect()->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/disbursement/status'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/disbursement/transfer'));
        $this->assertNotNull($cashout->fresh()->last_checked_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'injongepay_checked_at_bank', 'business_id' => $this->business->id]);
    }

    public function test_no_answer_on_a_cashout_says_so_and_can_be_pressed_again(): void
    {
        $cashout = $this->cashout($this->wallet());
        $this->fakeBank(['/disbursement/status' => Http::response('', 504)]);
        $this->asOwner();

        $this->post(route('injongepay.cashouts.check', $cashout))->assertSessionHas('error');

        $this->assertSame(Cashout::UNKNOWN, $cashout->fresh()->status);
        $this->get(route('injongepay.cashouts.show', $cashout))->assertSee(__('injongepay::injongepay.check_now'));
    }

    public function test_a_finished_cashout_has_nothing_to_ask(): void
    {
        $cashout = $this->cashout($this->wallet(), Cashout::COMPLETED);
        $this->fakeBank();
        $this->asOwner();

        $this->get(route('injongepay.cashouts.show', $cashout))->assertDontSee(__('injongepay::injongepay.check_now'));
    }

    public function test_a_pending_code_is_reloaded_from_its_page(): void
    {
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_PENDING]);
        $this->fakeBank(['/merchants/lookup' => Http::response(['responseCode' => '00', 'data' => ['merchant' => ['merchantId' => $wallet->merchant_code, 'status' => 'ACTIVE']]])]);
        $this->asOwner();

        $this->get(route('injongepay.wallets.show', $wallet->uuid))->assertSee(__('injongepay::injongepay.check_now'));
        $this->post(route('injongepay.wallets.check', $wallet->uuid))->assertSessionHas('success');

        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->fresh()->bank_status);
    }

    public function test_a_code_with_an_error_can_still_be_reloaded(): void
    {
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_FAILED, 'bank_error' => 'Something went wrong']);
        $this->fakeBank(['/merchants/lookup' => Http::response(['responseCode' => '00', 'data' => ['merchant' => ['merchantId' => $wallet->merchant_code, 'status' => 'ACTIVE']]])]);
        $this->asOwner();

        $this->get(route('injongepay.wallets.show', $wallet->uuid))->assertSee(__('injongepay::injongepay.check_now'));
        $this->post(route('injongepay.wallets.check', $wallet->uuid))->assertSessionHas('success');

        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->fresh()->bank_status);
        $this->assertNull($wallet->fresh()->bank_error);
    }

    public function test_a_pending_payment_is_confirmed_by_pressing_check(): void
    {
        $wallet = $this->wallet();
        $payment = $this->pending($wallet);
        $this->fakeBank(['/transactions' => Http::response([$this->row($wallet)])]);
        $this->asOwner();

        $this->get(route('injongepay.payments.show', $payment))->assertSee(__('injongepay::injongepay.check_now'));
        $this->post(route('injongepay.payments.check', $payment))->assertSessionHas('success', __('injongepay::injongepay.payment_check_confirmed'));

        $this->assertNotNull($payment->fresh()->cleared_at);
        $this->get(route('injongepay.payments.show', $payment))->assertDontSee(__('injongepay::injongepay.check_now'));
    }

    public function test_a_payment_gtbank_doesnt_list_stays_pending_and_says_why(): void
    {
        $payment = $this->pending($this->wallet());
        $this->fakeBank(['/transactions' => Http::sequence()->push([])->push('', 504)->push('', 504)->push('', 504)]);
        $this->asOwner();

        $this->post(route('injongepay.payments.check', $payment))->assertSessionHas('error', __('injongepay::injongepay.payment_check_not_listed'));
        $this->assertNull($payment->fresh()->cleared_at);

        $this->post(route('injongepay.payments.check', $payment))->assertSessionHas('error', __('injongepay::injongepay.payment_check_no_answer'));
    }

    public function test_another_business_cannot_press_these(): void
    {
        $wallet = $this->wallet();
        $cashout = $this->cashout($wallet);
        $payment = $this->pending($wallet);
        [, $stranger] = $this->makeBusiness(['business_name' => 'Someone else']);
        app(\App\Support\Tenancy\CurrentBusiness::class)->set($stranger->business_id);
        $this->actingAs($stranger->fresh(), 'staff');

        $this->post(route('injongepay.cashouts.check', $cashout))->assertNotFound();
        $this->post(route('injongepay.payments.check', $payment))->assertNotFound();
        $this->post(route('injongepay.wallets.check', $wallet->uuid))->assertNotFound();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/disbursement/status'));
    }

    public function test_inhills_retries_its_own_account_when_gtbank_gave_no_answer(): void
    {
        $account = new SettlementAccount(['kind' => 'wallet', 'account' => '0788811607', 'holder_name' => 'Inhills Ltd', 'bank_name' => 'MTN']);
        $account->forceFill(['business_id' => $this->operator->id, 'status' => SettlementAccount::PENDING])->save();
        $this->fakeBank(['/disbursement/lookup' => Http::response(['names' => 'Inhills Ltd', 'bank_code' => '040'])]);
        $this->asOperator();

        $this->get(route('injongepay.platform.earnings'))->assertSee(__('injongepay::injongepay.verify_at_bank'));
        $this->post(route('injongepay.platform.earnings.accounts.verify', $account))->assertSessionHasNoErrors();

        $this->assertSame(SettlementAccount::APPROVED, $account->fresh()->status);
    }
}
