<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\CashoutService;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\Reconciliation;
use Modules\InjongePay\Support\PlatformSettings;

class CashoutTest extends InjongePayTestCase
{
    private Wallet $till;

    private SettlementAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->till = $this->wallet();
        $this->account = new SettlementAccount(['kind' => 'wallet', 'account' => '0788811607', 'holder_name' => 'Rose Mary MUSABYIMANA', 'bank_name' => 'MTN']);
        $this->account->forceFill(['business_id' => $this->business->id, 'status' => SettlementAccount::APPROVED, 'verified_names' => 'Rose Mary MUSABYIMANA', 'approved_at' => now()->subDays(2), 'usable_from' => now()->subDay()])->save();
    }

    /** Payments GTBank confirmed, so the money is available. */
    private function fund(string ...$amounts): void
    {
        config(['injongepay.gtbank.base_url' => null]);
        foreach ($amounts as $i => $amount) {
            app(PaymentIntake::class)->receive(['transactionId' => 'TX-'.$i.'-'.$amount, 'merchantId' => $this->till->merchant_code, 'amount' => $amount, 'status' => 'SUCCESS'], Payment::VIA_BANK_PULL);
        }
    }

    private function cashout(int $rwf = 1000): Cashout
    {
        $approval = Approval::query()->create([
            'business_id' => $this->business->id, 'wallet_id' => $this->till->id, 'operation' => 'cashout', 'amount' => $rwf * 100,
            'payload' => ['settlement_account_id' => $this->account->id, 'account_label' => $this->account->label()], 'requested_by' => $this->owner->id, 'status' => Approval::APPROVED,
        ]);

        return app(CashoutService::class)->start($approval)->fresh();
    }

    private function acsc(array $overrides = []): array
    {
        return $overrides + ['status' => 'ACSC', 'document' => 'M00026711', 'request_id' => Cashout::query()->withoutGlobalScopes()->value('reference'), 'bank_code' => '078',
            'bank_name' => 'MTN MOBILE MONEY RWANDA LTD', 'receiver_id' => '0788811607', 'sender_id' => '2270038561', 'cbs_transaction_reference' => 'M00026711',
            'receiver_names' => 'Rose Mary MUSABYIMANA', 'created_at' => '2026-11-03T18:20:04.253982'];
    }

    private function check(Cashout $cashout): Cashout
    {
        app(CashoutService::class)->check($cashout->fresh());

        return $cashout->fresh();
    }

    public function test_paid_only_after_gtbank_confirms_twice(): void
    {
        $this->fund('20000.00');
        $statusCalls = 0;
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['identifier' => 'X', 'creditTime' => '2026-11-03T15:28:17Z', 'status' => 'PENDING']),
            '/disbursement/status' => function () use (&$statusCalls) {
                $statusCalls++;

                return Http::response($this->acsc());
            },
        ]);

        $cashout = $this->cashout(1000);
        $this->assertSame(Cashout::SUBMITTED, $cashout->status);
        $this->assertSame(1960000 - 100000, $this->till->fresh()->available_balance);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/disbursement/transfer') && $r['amount'] === 1000 && $r['beneficiaryAccount'] === '0788811607'
            && preg_match('/^CSH\d{14}$/', $r['reference']) && strlen($r['remarks']) <= 50);

        $this->assertSame(Cashout::SUBMITTED, $this->check($cashout)->status);
        $this->assertSame(Cashout::COMPLETED, $this->check($cashout)->status);
        $this->assertSame('M00026711', $cashout->fresh()->cbs_reference);
        $this->assertSame(0, app(Reconciliation::class)->platformDifference());
    }

    public function test_a_transfer_that_timed_out_but_went_through_is_paid_and_never_sent_twice(): void
    {
        $this->fund('20000.00');
        $transfers = 0;
        $this->fakeBank([
            '/disbursement/transfer' => function () use (&$transfers) {
                $transfers++;
                throw new ConnectionException('cURL error 28: Operation timed out after 15002 milliseconds with 0 bytes received');
            },
            '/disbursement/status' => fn () => Http::response($this->acsc()),
        ]);

        $cashout = $this->cashout(500);
        $this->assertSame([Cashout::UNKNOWN, 'unknown'], [$cashout->status, $cashout->submit_outcome]);

        $this->check($cashout);
        $this->artisan('injongepay:check-cashouts');
        $this->travel(2)->minutes();
        $this->artisan('injongepay:check-cashouts');

        $this->assertSame(Cashout::COMPLETED, $cashout->fresh()->status);
        $this->assertSame(1, $transfers);
    }

    public function test_digipay_refusing_gtbanks_login_waits_then_goes_to_a_person_not_back_to_the_business(): void
    {
        $this->fund('20000.00');
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['error' => 'Disbursement initiation failed: 401  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/pay": [no body]'], 500),
            '/disbursement/status' => Http::response(['error' => 'Unable to retrieve disbursement status: Status check failed: 400  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/check-status": "{"error":"Transaction not found for reference: X","status":"FAILED"}"'], 500),
        ]);

        $cashout = $this->cashout(1000);
        $this->assertSame('unknown', $cashout->submit_outcome);
        $this->check($cashout);
        $this->travel(30)->minutes();
        $this->assertSame(Cashout::UNKNOWN, $this->check($cashout)->status);
        $this->assertSame(1860000, $this->till->fresh()->available_balance);

        $this->travel(3)->hours();
        $this->assertSame(Cashout::NEEDS_REVIEW, $this->check($cashout)->status);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'cashout_unresolved')->exists());

        // The business can't settle it…
        $this->asOwner()->withConfirmedPassword()->post(route('injongepay.platform.cashouts.resolve', $cashout->uuid), ['outcome' => 'failed', 'note' => 'please give it back'])->assertForbidden();

        // …the platform does, with GTBank's confirmation.
        $this->flushSession();
        $this->asOperator()->withConfirmedPassword()->post(route('injongepay.platform.cashouts.resolve', $cashout->uuid), ['outcome' => 'failed', 'note' => 'GTBank (email 03/11) confirms DigiPay never created it'])->assertSessionHasNoErrors();
        $this->assertSame(Cashout::FAILED, $cashout->fresh()->status);
        $this->assertSame(1960000, $this->till->fresh()->available_balance);
        $this->assertSame(0, app(Reconciliation::class)->platformDifference());
    }

    public function test_refused_by_the_bridge_and_unknown_to_gtbank_returns_the_money_by_itself(): void
    {
        $this->fund('5000.00', '3000.00');
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['error' => 'Request Validation failed, please try again later or contact the system admin for further support'], 400),
            '/disbursement/status' => Http::response(['error' => 'Transaction not found for the provided reference']),
        ]);

        $cashout = $this->cashout(4900);
        $this->assertSame('refused', $cashout->submit_outcome);
        $this->assertSame(Payment::STATUS_CASHED_OUT, Payment::query()->withoutGlobalScopes()->orderBy('id')->value('payment_status'));

        $this->check($cashout);
        $this->assertSame(Cashout::UNKNOWN, $cashout->fresh()->status);
        $this->travel(11)->minutes();
        $this->assertSame(Cashout::FAILED, $this->check($cashout)->status);

        $this->assertSame(490000 + 294000, $this->till->fresh()->available_balance);
        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->where('payment_status', Payment::STATUS_CASHED_OUT)->count());
    }

    public function test_rejected_twice_returns_the_money(): void
    {
        $this->fund('20000.00');
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING']),
            '/disbursement/status' => fn () => Http::response($this->acsc(['status' => 'RJCT'])),
        ]);

        $cashout = $this->cashout(1000);
        $this->check($cashout);
        $this->assertSame(Cashout::SUBMITTED, $cashout->fresh()->status);
        $this->assertSame(Cashout::FAILED, $this->check($cashout)->status);
        $this->assertSame(1960000, $this->till->fresh()->available_balance);
    }

    public function test_a_status_for_another_amount_is_never_taken_as_paid(): void
    {
        $this->fund('20000.00');
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING']),
            '/disbursement/status' => fn () => Http::response($this->acsc(['amount' => 999])),
        ]);

        $cashout = $this->cashout(1000);

        $this->assertSame(Cashout::NEEDS_REVIEW, $this->check($cashout)->status);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'cashout_mismatch')->exists());
    }

    public function test_gtbanks_webhook_plus_a_matching_status_settles_it(): void
    {
        $this->fund('20000.00');
        PlatformSettings::set('webhook_allowed_ips', '10.22.198.46');
        $this->fakeBank([
            '/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING']),
            '/disbursement/status' => fn () => Http::response($this->acsc()),
        ]);
        $cashout = $this->cashout(1000);

        $this->withServerVariables(['REMOTE_ADDR' => '10.22.198.46'])->postJson('/api/gtbank/disbursement-status', [
            'identifier' => $cashout->reference, 'status' => 'ACSC', 'amount' => 1000, 'currency' => 'RWF', 'beneficiaryAccount' => '0788811607',
            'receiverNames' => 'Rose Mary MUSABYIMANA', 'creditTime' => '2026-11-03T15:28:17.006Z', 'completedAt' => '2026-11-03T15:28:25.123Z',
        ])->assertOk()->assertExactJson(['success' => true, 'message' => 'Acknowledged.', 'data' => null]);

        $this->assertSame(Cashout::COMPLETED, $cashout->fresh()->status);
    }

    public function test_a_webhook_for_another_account_changes_nothing(): void
    {
        $this->fund('20000.00');
        PlatformSettings::set('webhook_allowed_ips', '10.22.198.46');
        $this->fakeBank(['/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING'])]);
        $cashout = $this->cashout(1000);

        $this->withServerVariables(['REMOTE_ADDR' => '10.22.198.46'])->postJson('/api/gtbank/disbursement-status', ['identifier' => $cashout->reference, 'status' => 'ACSC', 'amount' => 1000, 'beneficiaryAccount' => '0788000000'])->assertOk();

        $this->assertSame(Cashout::SUBMITTED, $cashout->fresh()->status);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'cashout_mismatch')->exists());
    }

    public function test_only_the_payments_the_amount_covers_are_marked_cashed_out(): void
    {
        $this->fund('5000.00', '3000.00', '2000.00');
        $this->fakeBank(['/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING'])]);

        $this->cashout(6000);

        $this->assertSame([Payment::STATUS_CASHED_OUT, Payment::STATUS_AVAILABLE, Payment::STATUS_AVAILABLE],
            Payment::query()->withoutGlobalScopes()->orderBy('id')->pluck('payment_status')->all());
    }

    public function test_the_business_asks_with_its_pin_and_cannot_break_the_rules(): void
    {
        $this->fund('20000.00');
        app(\Modules\InjongePay\Services\Security\Pins::class)->set($this->owner, '48213');
        $this->fakeBank(['/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING'])]);
        $this->asOwner();
        $form = ['wallet' => $this->till->uuid, 'account' => $this->account->uuid, 'amount' => 1000, 'pin' => '48213'];

        $this->get(route('injongepay.cashouts.index'))->assertOk()->assertSee('MUSABYIMANA');
        $this->post(route('injongepay.cashouts.store'), ['amount' => '1000.50'] + $form)->assertSessionHasErrors('amount');
        $this->post(route('injongepay.cashouts.store'), ['amount' => 999999] + $form)->assertSessionHas('error');

        $this->post(route('injongepay.cashouts.store'), $form)->assertSessionHas('step_up');
        $code = null;
        Notification::assertSentTo($this->owner, \App\Notifications\BrandedNotification::class, function ($n) use (&$code) {
            preg_match('/\b(\d{6})\b/', json_encode($n), $m);
            $code = $m[1] ?? $code;

            return true;
        });
        $this->post(route('injongepay.cashouts.store'), $form + ['code' => $code])->assertRedirect();

        $cashout = Cashout::query()->withoutGlobalScopes()->firstOrFail();
        $this->get(route('injongepay.cashouts.show', $cashout))->assertOk()->assertSee($cashout->reference);
        $this->get(route('injongepay.platform.cashouts.index'))->assertForbidden();
    }

    public function test_the_form_starts_from_the_wallet_with_its_whole_balance_and_asks_for_a_pin_first(): void
    {
        $this->fund('20000.00');
        $this->asOwner();

        $this->get(route('injongepay.overview'))->assertSee(route('injongepay.cashouts.index', ['wallet' => $this->till->uuid]), false);
        $this->get(route('injongepay.cashouts.index', ['wallet' => $this->till->uuid]))->assertOk()
            ->assertSee('value="19600"', false)
            ->assertSee(__('injongepay::injongepay.set_pin_first'));

        app(\Modules\InjongePay\Services\Security\Pins::class)->set($this->owner, '48213');
        $this->get(route('injongepay.cashouts.index'))->assertDontSee(__('injongepay::injongepay.set_pin_first'));
    }

    public function test_an_account_gtbank_did_not_confirm_cannot_be_used(): void
    {
        $this->fund('20000.00');
        $this->account->forceFill(['status' => SettlementAccount::PENDING])->save();

        $this->expectException(\Modules\InjongePay\Exceptions\InjongePayException::class);
        app(CashoutService::class)->validate($this->till->fresh(), $this->account->fresh(), 100000);
    }

    public function test_operator_screens_show_the_whole_story(): void
    {
        $this->fund('20000.00');
        $this->fakeBank(['/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING'])]);
        $cashout = $this->cashout(1000);

        $this->asOperator();
        $this->get(route('injongepay.platform.cashouts.index', ['status' => 'open']))->assertOk()->assertSee($cashout->reference);
        $this->get(route('injongepay.platform.cashouts.show', $cashout->uuid))->assertOk()->assertSee('/disbursement/transfer')->assertSee(__('injongepay::injongepay.cashout_SUBMITTED'));
    }

    public function test_inhills_cashes_out_its_fee_earnings_like_a_business(): void
    {
        $this->fund('20000.00');
        app(\Modules\InjongePay\Services\FeeSweep::class)->run();
        $revenue = app(\Modules\InjongePay\Services\InternalAccounts::class)->platformRevenue()->fresh();
        $this->assertSame(40000, $revenue->available_balance);
        app(\Modules\InjongePay\Services\Security\Pins::class)->set($this->operatorAdmin, '48213');
        $this->asOperator();

        $this->get(route('injongepay.platform.earnings'))->assertOk()->assertSee('RWF 400');
        $this->post(route('injongepay.platform.earnings.accounts.store'), ['kind' => 'bank', 'account' => '4005101091278', 'holder_name' => 'Inhills Technology Ltd', 'pin' => '48213'])->assertSessionHas('success');
        $account = SettlementAccount::query()->where('business_id', $this->operator->id)->firstOrFail();

        $form = ['wallet' => $revenue->uuid, 'account' => $account->uuid, 'amount' => 400, 'pin' => '48213'];
        $this->post(route('injongepay.platform.earnings.cashout'), $form)->assertSessionHas('step_up');
        $code = null;
        Notification::assertSentTo($this->operatorAdmin, \App\Notifications\BrandedNotification::class, function ($n) use (&$code) {
            preg_match('/\b(\d{6})\b/', json_encode($n), $m);
            $code = $m[1] ?? $code;

            return true;
        });
        $this->post(route('injongepay.platform.earnings.cashout'), $form + ['code' => $code])->assertRedirect();

        $this->assertSame(0, $revenue->fresh()->available_balance);
        $this->assertSame(Cashout::COMPLETED, Cashout::query()->withoutGlobalScopes()->where('wallet_id', $revenue->id)->value('status'));
        $this->assertSame(0, app(Reconciliation::class)->platformDifference());

        // A business can never use the platform's wallets or this page.
        $this->flushSession();
        $this->asOwner()->get(route('injongepay.platform.earnings'))->assertForbidden();
        $this->post(route('injongepay.cashouts.store'), ['wallet' => $revenue->uuid, 'account' => $this->account->uuid, 'amount' => 1, 'pin' => '48213'])->assertNotFound();
        $this->assertSame(1, Cashout::query()->withoutGlobalScopes()->where('wallet_id', $revenue->id)->count());
    }
}
