<?php

namespace Modules\InjongePay\Tests\Feature;

use LogicException;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Services\Fees;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Support\Counter;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformSettings;

class MoneyAndLedgerTest extends InjongePayTestCase
{
    public function test_money_is_whole_cents_and_rounds_like_production(): void
    {
        $this->assertSame(980050, Money::toCents('9800.50'));
        $this->assertSame(1000000, Money::toCents(10000));
        $this->assertSame('9800.50', Money::toDecimal(980050));
        // round(333.33 × 2 / 100, 2) = 6.67 in production.
        $this->assertSame(667, Money::percentOf(33333, '2.00'));
        $this->assertSame(0, Money::percentOf(1, '2.00'));

        $this->expectException(\InvalidArgumentException::class);
        Money::toCents('1.005');
    }

    public function test_fee_split_uses_the_business_rate_then_the_platform_rate(): void
    {
        $wallet = $this->wallet();
        $fees = app(Fees::class);

        // 10,000 RWF at 2%, bank cut 0%: platform +200, business +9,800.
        $this->assertSame(['rate' => '2.0000', 'fee' => 20000, 'bank_cut' => 0, 'platform' => 20000, 'net' => 980000], $fees->split($wallet, 1000000));

        PlatformSettings::set('bank_cut_percentage', '25');
        $this->profile(overrides: ['fee_percentage' => '1.50']);
        $split = $fees->split($wallet, 1000000);
        $this->assertSame(['rate' => '1.5000', 'fee' => 15000, 'bank_cut' => 3750, 'platform' => 11250, 'net' => 985000], $split);
        $this->assertSame($split['fee'], $split['bank_cut'] + $split['platform']);

        BusinessProfile::query()->withoutGlobalScopes()->update(['fee_percentage' => '100']);
        $this->expectException(InjongePayException::class);
        $fees->split($wallet, 1000000);
    }

    public function test_rates_can_have_four_decimals(): void
    {
        PlatformSettings::set('fee_percentage', '0.25');
        PlatformSettings::set('bank_cut_percentage', '0.025');

        // 100,000 RWF at 0.25% = 250 RWF fee; 0.025% of that = 0.06 RWF to the bank.
        $split = app(Fees::class)->split($this->wallet(), 10000000);

        $this->assertSame(['rate' => '0.2500', 'fee' => 25000, 'bank_cut' => 6, 'platform' => 24994, 'net' => 9975000], $split);
        $this->assertSame('0.025', \Modules\InjongePay\Support\Rate::display(PlatformSettings::bankCutPercentage()));
    }

    public function test_ledger_moves_pending_to_available_and_never_overdraws(): void
    {
        $wallet = $this->wallet();
        $ledger = app(Ledger::class);

        $ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::PENDING, 980000, 'payment', 'pay:1');
        $this->assertSame([980000, 0], [$wallet->pending_balance, $wallet->available_balance]);

        $ledger->clear($wallet, 980000, 'payment_cleared', 'clear:1', ['reference' => 'PAY20261103000001']);
        $this->assertSame([0, 980000], [$wallet->fresh()->pending_balance, $wallet->fresh()->available_balance]);
        $this->assertTrue(LedgerEntry::query()->withoutGlobalScopes()->where('reference', 'PAY20261103000001P')->exists());

        try {
            $ledger->post($wallet, LedgerEntry::DEBIT, LedgerEntry::AVAILABLE, 980001, 'cashout', 'csh:1');
            $this->fail('Overdraft allowed');
        } catch (InjongePayException) {
        }

        $this->assertSame(['pending' => 0, 'available' => 980000], $ledger->recompute($wallet));
        $this->assertSame(980000, $wallet->fresh()->available_balance);
    }

    public function test_the_same_idempotency_key_posts_once(): void
    {
        $wallet = $this->wallet();
        $ledger = app(Ledger::class);

        $first = $ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 500, 'payment', 'same-key');
        $again = $ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 500, 'payment', 'same-key');

        $this->assertTrue($first->is($again));
        $this->assertSame(500, $wallet->fresh()->available_balance);
    }

    public function test_ledger_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = app(Ledger::class)->post($this->wallet(), LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 500, 'payment', 'k');

        $this->expectException(LogicException::class);
        $entry->update(['amount' => 1]);
    }

    public function test_references_are_letters_and_digits_and_never_repeat(): void
    {
        $a = Counter::reference('CSH');
        $b = Counter::reference('CSH');

        $this->assertMatchesRegularExpression('/^CSH\d{14}$/', $a);
        $this->assertLessThanOrEqual(35, strlen($a));
        $this->assertNotSame($a, $b);
    }

    public function test_internal_wallets_belong_to_the_operator(): void
    {
        $accounts = app(InternalAccounts::class);
        $cut = $accounts->bankCut();
        $revenue = $accounts->platformRevenue();

        $this->assertSame($this->operator->id, $cut->business_id);
        $this->assertSame('900001', $cut->merchant_code);
        $this->assertSame('900002', $revenue->merchant_code);
        $this->assertTrue($accounts->bankCut()->is($cut));
        $this->assertFalse($cut->acceptsPayments());
        $this->assertSame('55500001', $this->wallet()->merchant_code);
    }
}
