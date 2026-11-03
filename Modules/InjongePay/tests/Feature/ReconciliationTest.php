<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Models\DailyClose;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Services\BankMirror;
use Modules\InjongePay\Services\FeeSweep;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\Reconciliation;

class ReconciliationTest extends InjongePayTestCase
{
    private function row(string $id, string $code, string $amount = '10000.00', string $status = 'SUCCESS', string $date = '2026-11-02'): array
    {
        return ['id' => 1, 'transactionId' => $id, 'merchantId' => $code, 'amount' => $amount, 'status' => $status, 'valueDate' => $date,
            'participantName' => 'INHILLS', 'initiatingPartyName' => 'MTN', 'debtorName' => 'JOHN DOE', 'debtorAccountId' => '250788111222', 'creditorAccountId' => $code, 'createdAt' => $date.'T09:55:19.191888'];
    }

    private function webhookPayment(string $id, string $code, string $amount = '10000.00'): Payment
    {
        return app(PaymentIntake::class)->receive(['transactionId' => $id, 'merchantId' => $code, 'amount' => $amount, 'valueDate' => '2026-11-02', 'status' => 'SUCCESS'], Payment::VIA_WEBHOOK)[1];
    }

    public function test_gtbanks_list_confirms_our_payment_and_takes_in_the_one_we_missed(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank();
        $this->webhookPayment('TX-1', $wallet->merchant_code);
        $this->fakeBank(['/transactions' => Http::response([$this->row('TX-1', $wallet->merchant_code), $this->row('TX-2', $wallet->merchant_code, '5000.00')])]);

        $summary = app(BankMirror::class)->pull($wallet, now()->subDay(), now());

        $this->assertSame(1, $summary['matched']);
        $this->assertSame(1, $summary['ingested']);
        $this->assertSame([0, 980000 + 490000], [$wallet->fresh()->pending_balance, $wallet->fresh()->available_balance]);
        $this->assertSame(Payment::VIA_BANK_PULL, Payment::query()->withoutGlobalScopes()->where('bank_reference', 'TX-2')->value('received_via'));
    }

    public function test_a_different_amount_is_never_confirmed(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank();
        $this->webhookPayment('TX-1', $wallet->merchant_code, '10000.00');
        $this->fakeBank(['/transactions' => Http::response([$this->row('TX-1', $wallet->merchant_code, '1000.00')])]);

        app(BankMirror::class)->pull($wallet, now()->subDay(), now());

        $this->assertSame(0, $wallet->fresh()->available_balance);
        $this->assertSame(1, ReconciliationItem::query()->where('kind', 'amount_mismatch')->count());
    }

    public function test_rows_about_another_merchant_code_are_ignored(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/transactions' => Http::response([$this->row('TX-X', '55599999')])]);

        $this->assertSame(1, app(BankMirror::class)->pull($wallet, now()->subDay(), now())['foreign']);
        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->count());
    }

    public function test_no_answer_from_gtbank_changes_nothing(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/transactions' => fn () => throw new ConnectionException('down')]);

        $this->assertNull(app(BankMirror::class)->pull($wallet, now()->subDay(), now()));
    }

    public function test_a_reversal_by_gtbank_takes_the_money_and_fees_back(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/transactions' => Http::sequence()
            ->push([$this->row('TX-1', $wallet->merchant_code)])
            ->push([$this->row('TX-1', $wallet->merchant_code, status: 'REVERSED')])]);
        app(BankMirror::class)->pull($wallet, now()->subDay(), now());
        app(FeeSweep::class)->run();
        $this->assertSame(20000, app(InternalAccounts::class)->platformRevenue()->fresh()->available_balance);

        app(BankMirror::class)->pull($wallet, now()->subDay(), now());

        $this->assertSame(Payment::STATUS_REVERSED, Payment::query()->withoutGlobalScopes()->value('payment_status'));
        $this->assertSame(0, $wallet->fresh()->balance());
        $this->assertSame(0, app(InternalAccounts::class)->platformRevenue()->fresh()->available_balance);
        $this->assertSame(0, app(Reconciliation::class)->platformDifference());
    }

    public function test_a_reversal_after_the_money_left_goes_to_the_platform_not_below_zero(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/transactions' => Http::sequence()
            ->push([$this->row('TX-1', $wallet->merchant_code)])
            ->push([$this->row('TX-1', $wallet->merchant_code, status: 'REVERSED')])]);
        app(BankMirror::class)->pull($wallet, now()->subDay(), now());
        // The business spent it (cash-outs come later; a direct debit stands in here).
        app(\Modules\InjongePay\Services\Ledger::class)->post($wallet->fresh(), 'debit', 'available', 980000, 'cashout', 'test-out');

        app(BankMirror::class)->pull($wallet, now()->subDay(), now());

        $this->assertSame(0, $wallet->fresh()->available_balance);
        $this->assertSame(980000, ReconciliationItem::query()->where('kind', 'reversal_shortfall')->value('amount'));
    }

    public function test_closing_a_day_compares_count_and_total_with_gtbank(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank();
        $this->webhookPayment('TX-1', $wallet->merchant_code);
        $this->webhookPayment('TX-GHOST', $wallet->merchant_code, '700.00');
        $this->fakeBank(['/transactions' => Http::response([$this->row('TX-1', $wallet->merchant_code)])]);

        $close = app(Reconciliation::class)->closeDay($wallet, now()->setDate(2026, 11, 2));

        $this->assertSame(DailyClose::DIFFERENCES, $close->state);
        $this->assertSame([1, 1000000, 2, 1070000], [$close->bank_count, $close->bank_total, $close->our_count, $close->our_total]);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'missing_at_bank')->exists());
        // The payment GTBank doesn't list was never made available.
        $this->assertSame(70000 - 1400, $wallet->fresh()->pending_balance);
    }

    public function test_a_day_that_agrees_is_reconciled(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank();
        $this->webhookPayment('TX-1', $wallet->merchant_code);
        $this->fakeBank(['/transactions' => Http::response([$this->row('TX-1', $wallet->merchant_code)])]);

        $this->assertSame(DailyClose::RECONCILED, app(Reconciliation::class)->closeDay($wallet, now()->setDate(2026, 11, 2))->state);
    }

    public function test_books_check_finds_a_balance_written_outside_the_ledger(): void
    {
        $wallet = $this->wallet();
        DB::table('ijp_wallets')->where('id', $wallet->id)->update(['available_balance' => 500]);

        $result = app(Reconciliation::class)->checkBooks();

        $this->assertSame(1, $result['drifts']);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'balance_drift')->exists());
        $this->assertSame(-500, $result['platform_difference']);
    }

    public function test_operator_closes_a_day_and_explains_a_difference_from_the_screen(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/transactions' => Http::response([])]);
        $this->fakeBank();
        $this->webhookPayment('TX-GHOST', $wallet->merchant_code);
        $this->fakeBank(['/participant/transactions' => Http::response([])]);
        $this->asOperator();

        $this->post(route('injongepay.platform.reconciliation.close-day'), ['day' => '2026-11-02'])->assertRedirect();
        $item = ReconciliationItem::query()->where('kind', 'missing_at_bank')->firstOrFail();
        $this->get(route('injongepay.platform.reconciliation.index', ['day' => '2026-11-02']))->assertOk()->assertSee(__('injongepay::injongepay.item_missing_at_bank'));

        $this->withConfirmedPassword()->post(route('injongepay.platform.reconciliation.resolve', $item), ['note' => 'GTBank confirmed by phone it is a test'])->assertSessionHasNoErrors();
        $this->assertSame(ReconciliationItem::RESOLVED, $item->fresh()->status);

        $this->flushSession();
        $this->asOwner()->get(route('injongepay.platform.reconciliation.index'))->assertForbidden();
    }
}
