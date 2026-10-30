<?php

namespace Modules\Savings\Tests\Feature;

use LogicException;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Exceptions\LikelyDuplicate;
use Modules\Savings\Models\Line;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Budgets;
use Modules\Savings\Services\Reports;
use Modules\Savings\Support\Money;

class LedgerTest extends SavingsTestCase
{
    public function test_at01_any_number_of_accounts_with_balances_from_the_ledger(): void
    {
        $accounts = collect(range(1, 12))->map(fn ($i) => $this->account("Account {$i}", $i * 1000));

        $this->assertCount(12, $accounts);
        $this->assertSame(12000, $this->balance($accounts->last()));
    }

    public function test_at02_at03_income_and_expense_update_account_and_reports(): void
    {
        $bank = $this->account('Bank', 100000);
        $salary = $this->category('Salary', 'income');
        $food = $this->category('Food');

        $this->ops()->income($bank, 450000, $salary, ['effective_date' => $this->day()]);
        $this->ops()->expense($bank, 30000, $food, ['effective_date' => $this->day()]);

        $this->assertSame(520000, $this->balance($bank));
        $budgets = app(Budgets::class);
        $this->assertSame([$salary->id => 450000], $budgets->totals('income', $this->day(-1), $this->day(1)));
        $this->assertSame([$food->id => 30000], $budgets->totals('expense', $this->day(-1), $this->day(1)));
    }

    public function test_at04_transfer_moves_equal_amounts_and_is_not_spending(): void
    {
        $bank = $this->account('Bank', 500000);
        $savings = $this->account('Savings', 0, ['type' => 'savings', 'is_liquid' => false]);

        $this->ops()->transfer($bank, $savings, 200000, ['effective_date' => $this->day()]);

        $this->assertSame(300000, $this->balance($bank));
        $this->assertSame(200000, $this->balance($savings));
        $this->assertSame([], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)), 'a transfer is never spending');
        $this->assertSame(0, Line::query()->count());
    }

    public function test_transfer_fee_is_an_expense_and_money_is_not_created(): void
    {
        $bank = $this->account('Bank', 100000);
        $momo = $this->account('MoMo');
        $fees = $this->category('Fees');

        $this->ops()->transfer($bank, $momo, 50000, ['effective_date' => $this->day()], fee: 500, feeCategory: $fees);

        $this->assertSame(49500, $this->balance($bank));
        $this->assertSame(50000, $this->balance($momo));
        $this->assertSame([$fees->id => 500], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)));
    }

    public function test_at07_refund_is_linked_and_reduces_spending(): void
    {
        $bank = $this->account('Bank', 100000);
        $shopping = $this->category('Shopping');
        $purchase = $this->ops()->expense($bank, 40000, $shopping, ['effective_date' => $this->day()]);

        $refund = $this->ops()->refund($purchase, $bank, 15000, ['effective_date' => $this->day()]);

        $this->assertTrue($refund->refundOf->is($purchase));
        $this->assertSame(75000, $this->balance($bank));
        $this->assertSame([$shopping->id => 25000], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)));

        $this->expectException(LedgerException::class);
        $this->ops()->refund($purchase, $bank, 25001, ['effective_date' => $this->day()]);
    }

    public function test_at08_rent_paid_from_savings_and_reimbursed_counts_once(): void
    {
        $income = $this->account('Income account', 600000);
        $savings = $this->account('Savings', 400000, ['type' => 'savings']);
        $rent = $this->category('Rent');

        $this->ops()->expense($savings, 250000, $rent, ['effective_date' => $this->day()]);
        $this->ops()->transfer($income, $savings, 250000, ['effective_date' => $this->day(), 'description' => 'Reimburse rent']);

        $this->assertSame(350000, $this->balance($income));
        $this->assertSame(400000, $this->balance($savings));
        $this->assertSame([$rent->id => 250000], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)), 'rent once');
        $this->assertSame([], app(Budgets::class)->totals('income', $this->day(-1), $this->day(1)), 'no income');
    }

    public function test_at14_at20_correction_reverses_keeps_history_and_reports_agree(): void
    {
        $bank = $this->account('Bank', 100000);
        $food = $this->category('Food');
        $tx = $this->ops()->expense($bank, 12000, $food, ['effective_date' => $this->day(), 'description' => 'Groceries']);

        $new = $this->ledger()->correct($tx, 'Typed 12,000 instead of 21,000', [
            'type' => 'expense', 'effective_date' => $this->day(), 'description' => 'Groceries', 'idempotency_key' => 'fix-1',
            'entries' => [['account' => $bank, 'amount' => -21000]],
            'lines' => [['nature' => 'expense', 'amount' => 21000, 'category' => $food]],
        ]);

        $this->assertSame(79000, $this->balance($bank));
        $this->assertTrue($tx->fresh()->isReversed());
        $this->assertSame($tx->id, $new->replaces_id);
        $this->assertSame(3, Transaction::query()->count(), 'original, reversal, replacement all kept');
        $this->assertSame([$food->id => 21000], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)));

        $report = app(Reports::class)->run('spending', $this->day(-1), $this->day(1));
        $this->assertSame(Money::format(21000), $report['totals'][__('savings::savings.total')]);

        $this->expectException(LedgerException::class);
        $this->ledger()->reverse($tx->fresh(), 'again');
    }

    public function test_posted_transactions_cannot_be_edited_or_deleted(): void
    {
        $bank = $this->account('Bank', 1000);
        $tx = $this->ops()->expense($bank, 100, $this->category(), ['effective_date' => $this->day()]);

        $this->expectException(LogicException::class);
        $tx->forceFill(['amount' => 5])->save();
    }

    public function test_at17_retrying_the_same_operation_posts_once(): void
    {
        $bank = $this->account('Bank', 0);
        $salary = $this->category('Salary', 'income');

        $first = $this->ops()->income($bank, 1000, $salary, ['effective_date' => $this->day(), 'idempotency_key' => 'form-abc']);
        $again = $this->ops()->income($bank, 1000, $salary, ['effective_date' => $this->day(), 'idempotency_key' => 'form-abc']);

        $this->assertTrue($first->is($again));
        $this->assertSame(1000, $this->balance($bank));
    }

    public function test_likely_duplicates_need_confirmation(): void
    {
        $bank = $this->account('Bank', 100000);
        $food = $this->category();
        $this->ops()->expense($bank, 5000, $food, ['effective_date' => $this->day()]);

        try {
            $this->ops()->expense($bank, 5000, $food, ['effective_date' => $this->day(1)]);
            $this->fail('Duplicate not flagged');
        } catch (LikelyDuplicate $e) {
            $this->assertCount(1, $e->matches);
        }

        $this->ops()->expense($bank, 5000, $food, ['effective_date' => $this->day(1), 'allow_duplicate' => true]);
        $this->assertSame(90000, $this->balance($bank));
    }

    public function test_at19_foreign_currency_keeps_original_amount_and_rate(): void
    {
        $rwf = $this->account('Bank RWF', 2000000);
        $usd = $this->account('Dollar account', 0, ['currency' => 'USD']);

        $tx = $this->ops()->transfer($rwf, $usd, 1300000, ['effective_date' => $this->day()], received: 100000);

        $this->assertSame(700000, $this->balance($rwf));
        $this->assertSame(100000, $this->balance($usd), 'USD 1,000.00');
        $this->assertSame('USD', $tx->original_currency);
        $this->assertSame(100000, $tx->original_amount);
        $this->assertNotNull($tx->exchange_rate);
        $this->assertSame('USD 1,000.00', Money::format($this->balance($usd), 'USD'));
    }

    public function test_ledger_rules_are_enforced(): void
    {
        $bank = $this->account('Bank', 1000);
        $archived = $this->account('Old', 0, ['status' => 'archived']);
        $income = $this->category('Salary', 'income');

        foreach ([
            fn () => $this->ops()->expense($bank, 100, $income, ['effective_date' => $this->day()]),               // wrong category kind
            fn () => $this->ops()->expense($archived, 100, $this->category('X'), ['effective_date' => $this->day()]), // archived
            fn () => $this->ops()->expense($bank, 100, $this->category('Y'), ['effective_date' => now()->subYears(2)->toDateString()]), // before opening
            fn () => $this->ledger()->post(['type' => 'expense', 'effective_date' => $this->day(), 'description' => 'x', 'idempotency_key' => 'u1',
                'entries' => [['account' => $bank, 'amount' => -100]], 'lines' => [['nature' => 'expense', 'amount' => 90, 'category' => $this->category('Z')]]]), // unbalanced
            fn () => $this->ops()->transfer($bank, $bank, 10, ['effective_date' => $this->day()]),
        ] as $i => $attempt) {
            try {
                $attempt();
                $this->fail("Rule {$i} not enforced");
            } catch (LedgerException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1000, $this->balance($bank));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_money_is_exact(): void
    {
        $this->assertSame(1250, Money::parse('12.50', 'USD'));
        $this->assertSame(25600, Money::parse('25,600', 'RWF'));
        $this->assertSame(-375, Money::parse('-3.75', 'USD'));
        $this->assertSame('RWF 250,600', Money::format(250600, 'RWF'));
        $this->assertSame('12.50', Money::toDecimal(1250, 'USD'));
        $this->assertSame(130000, Money::convert(10000, 'USD', 'RWF', '1300'), 'USD 100 at 1,300');

        $this->expectException(\InvalidArgumentException::class);
        Money::parse('10.5', 'RWF');
    }
}
