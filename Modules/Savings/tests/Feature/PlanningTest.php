<?php

namespace Modules\Savings\Tests\Feature;

use App\Support\Settings\BusinessSettings;
use Illuminate\Support\Carbon;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Budgets;
use Modules\Savings\Services\Periods;

class PlanningTest extends SavingsTestCase
{
    public function test_at05_scenario2_salary_in_two_parts(): void
    {
        $bank = $this->account('Bank', 100000);
        $salary = $this->category('Salary', 'income');
        [$first, $second] = $this->planning()->expectInstalments($bank, $salary, 'Salary October', [
            ['date' => $this->day(), 'amount' => 450000], ['date' => $this->day(10), 'amount' => 550000],
        ]);

        $this->assertSame(100000, $this->balance($bank), 'expected income is not cash');

        $tx = $this->planning()->post($first);
        $again = $this->planning()->post($first->fresh());
        $this->assertTrue($tx->is($again), 'marking received twice posts once');
        $this->assertSame(550000, $this->balance($bank), 'second part not included yet');
        $this->assertSame('expected', $second->fresh()->status);
    }

    public function test_at06_scenario7_overpayment_and_partial_repayment(): void
    {
        $bank = $this->account('Bank', 1000000);
        $groceries = $this->category('Groceries');
        $receivable = $this->obligations()->create(['direction' => 'receivable', 'counterparty' => 'Jean', 'description' => 'Overpaid Jean', 'currency' => 'RWF']);

        $this->ops()->splitPayment($bank, 25600, $groceries, 225000, $receivable, ['effective_date' => $this->day()]);

        $this->assertSame(749400, $this->balance($bank));
        $this->assertSame(225000, $this->obligations()->outstanding($receivable));
        $this->assertSame([$groceries->id => 25600], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)), 'only the real cost is spending');

        $this->obligations()->repay($receivable, $bank, 100000, ['effective_date' => $this->day()]);
        $this->assertSame(849400, $this->balance($bank));
        $this->assertSame(125000, $this->obligations()->outstanding($receivable));
        $this->assertSame('partly_repaid', $this->obligations()->state($receivable));
        $this->assertSame([], app(Budgets::class)->totals('income', $this->day(-1), $this->day(1)), 'a repayment is not income');

        $this->expectException(LedgerException::class);
        $this->obligations()->repay($receivable, $bank, 125001, ['effective_date' => $this->day()]);
    }

    public function test_someone_owes_an_opening_amount_and_it_can_be_settled_or_written_off(): void
    {
        $bank = $this->account('Bank');
        $debt = $this->obligations()->create(['direction' => 'receivable', 'counterparty' => 'Eric', 'description' => 'Loan to Eric', 'currency' => 'RWF', 'due_date' => $this->day(-1)], 100000);

        $this->assertSame('overdue', $this->obligations()->state($debt));
        $this->obligations()->repay($debt, $bank, 100000, ['effective_date' => $this->day()]);
        $this->assertSame('settled', $this->obligations()->state($debt));

        $other = $this->obligations()->create(['direction' => 'receivable', 'counterparty' => 'Ann', 'description' => 'Ann', 'currency' => 'RWF'], 50000);
        $this->obligations()->writeOff($other, 'Gift');
        $this->assertSame(0, $this->obligations()->outstanding($other));
        $this->assertSame('written_off', $this->obligations()->state($other));
    }

    public function test_scenario6_and_8_restore_target_from_selected_transactions(): void
    {
        $savings = $this->account('Savings', 500000, ['type' => 'savings']);
        $phone = $this->account('Phone', 0, ['type' => 'mobile_money']);
        $income = $this->account('Income', 300000);

        $out1 = $this->ops()->transfer($savings, $phone, 120000, ['effective_date' => $this->day(-5)]);
        $out2 = $this->ops()->transfer($savings, $phone, 80000, ['effective_date' => $this->day(-3)]);
        $this->assertSame([], app(Budgets::class)->totals('expense', $this->day(-10), $this->day()), 'taking money out is not spending');

        $target = $this->obligations()->create(['direction' => 'restore', 'description' => 'Put back into savings', 'currency' => 'RWF', 'account_id' => $savings->id]);
        $this->obligations()->addBasis($target, $out1, 120000);
        $this->obligations()->addBasis($target, $out2, 50000, 'Only 50k of this one counts');
        $this->assertSame(170000, $this->obligations()->outstanding($target));

        try {
            $this->obligations()->addBasis($target, $out1, 10000);
            $this->fail('Counted twice');
        } catch (LedgerException) {
        }

        $this->obligations()->restore($target, $income, 70000, ['effective_date' => $this->day()]);
        $this->assertSame(370000, $this->balance($savings), 'restoring raises the balance through a posted transfer');
        $this->assertSame(100000, $this->obligations()->outstanding($target));
        $this->assertSame([], app(Budgets::class)->totals('income', $this->day(-10), $this->day()), 'no fictitious income');
        $this->assertCount(3, $this->obligations()->history($target), 'every step explained');

        // Correcting the original transfer keeps the target honest.
        $this->ledger()->reverse($out1->fresh(), 'Was a mistake');
        // Only 50,000 still counts and 70,000 was already put back: 20,000 more than needed.
        $this->assertSame(-20000, $this->obligations()->outstanding($target->fresh()));
    }

    public function test_at09_scenario5_goal_earmark_does_not_move_money(): void
    {
        $bank = $this->account('Bank', 400000);
        $savings = $this->account('Savings', 0, ['type' => 'savings']);
        $goal = new Goal(['name' => 'Emergency fund', 'target_amount' => 1000000, 'currency' => 'RWF', 'account_id' => $savings->id, 'status' => 'active']);
        $goal->save();
        $other = new Goal(['name' => 'Laptop', 'target_amount' => 800000, 'currency' => 'RWF', 'status' => 'active']);
        $other->save();

        $this->planning()->allocate($goal, 150000);
        $this->assertSame(0, $this->balance($savings), 'earmarking is a plan');
        $this->assertSame(150000, $this->planning()->earmarked($goal));

        $this->planning()->contribute($goal, $bank, 100000, ['effective_date' => $this->day()]);
        $this->assertSame(100000, $this->balance($savings));
        $this->assertSame(100000, $this->planning()->funded($goal));
        $this->assertSame([], app(Budgets::class)->totals('expense', $this->day(-1), $this->day(1)), 'saving is not spending');

        $this->planning()->reassign($goal, $other, 50000, 'Laptop first');
        $this->assertSame(100000, $this->planning()->earmarked($goal));
        $this->assertSame(50000, $this->planning()->earmarked($other));
    }

    public function test_at10_scenario3_custom_period_28th_to_27th(): void
    {
        app(BusinessSettings::class)->set('savings.period', ['type' => 'monthly', 'start_day' => 28]);
        $periods = app(Periods::class);

        [$start, $end] = $periods->for('2026-11-02');
        $this->assertSame(['2026-10-28', '2026-11-27'], [$start->toDateString(), $end->toDateString()]);
        [$start, $end] = $periods->for('2026-10-28');
        $this->assertSame('2026-10-28', $start->toDateString());
        [$start, $end] = $periods->for('2027-01-05');
        $this->assertSame(['2026-12-28', '2027-01-27'], [$start->toDateString(), $end->toDateString()], 'across a year');

        app(BusinessSettings::class)->set('savings.period', ['type' => 'monthly', 'start_day' => 31]);
        [$start, $end] = app(Periods::class)->for('2028-02-29');
        $this->assertSame(['2028-02-29', '2028-03-30'], [$start->toDateString(), $end->toDateString()], 'leap year, short month');

        app(BusinessSettings::class)->set('savings.period', ['type' => 'weekly', 'weekday' => 1]);
        [$start, $end] = app(Periods::class)->for('2026-10-29');
        $this->assertSame(['2026-10-26', '2026-11-01'], [$start->toDateString(), $end->toDateString()]);

        // A transaction on 2 November belongs to the period that started on 28 October.
        app(BusinessSettings::class)->set('savings.period', ['type' => 'monthly', 'start_day' => 28]);
        $bank = $this->account('Bank', 100000, ['opening_date' => '2026-01-01']);
        $food = $this->category();
        $this->ops()->expense($bank, 8000, $food, ['effective_date' => '2026-11-02']);
        [$start, $end] = app(Periods::class)->for('2026-11-02');
        $row = app(Budgets::class)->summary($start, $end)->firstWhere('category.id', $food->id);
        $this->assertSame(8000, $row['spent']);
    }

    public function test_at11_scenario10_commitment_lowers_available_not_cash(): void
    {
        $bank = $this->account('Bank', 300000, ['minimum_balance' => 20000]);
        $commitment = $this->planning()->commit($bank, 'Ikimina contribution', 100000, $this->day(5));

        $available = $this->planning()->available($bank);
        $this->assertSame(300000, $available['balance']);
        $this->assertSame(180000, $available['available'], '300k − 100k committed − 20k minimum');

        $this->planning()->settleCommitment($commitment, $this->category('Contributions'));
        $this->assertSame(200000, $this->balance($bank));
        $this->assertSame(180000, $this->planning()->available($bank)['available']);
    }

    public function test_at18_scenario11_recurring_rule_changes_only_future_events(): void
    {
        $bank = $this->account('Bank', 1000000);
        $internet = $this->category('Internet');
        $rule = $this->planning()->saveRule(new RecurringRule, [
            'kind' => 'expense', 'description' => 'Internet', 'account_id' => $bank->id, 'category_id' => $internet->id,
            'amount' => 30000, 'frequency' => 'month', 'interval' => 1, 'start_date' => $this->day(), 'confidence' => 'confirmed', 'is_active' => true,
        ]);

        $events = Expected::query()->where('recurring_rule_id', $rule->id)->orderBy('expected_date')->get();
        $this->assertGreaterThanOrEqual(3, $events->count());
        $paid = $this->planning()->post($events->first());

        $this->planning()->saveRule($rule, ['amount' => 35000]);

        $this->assertSame(30000, $paid->fresh()->amount, 'recorded payment unchanged');
        $this->assertSame('posted', $events->first()->fresh()->status);
        $this->assertSame([35000], Expected::query()->where('recurring_rule_id', $rule->id)->where('status', 'expected')->pluck('amount')->unique()->values()->all());
        $this->assertSame(1, Transaction::query()->count(), 'no duplicate posting');
    }

    public function test_scenario11_already_recorded_bill_is_matched_not_posted_again(): void
    {
        $bank = $this->account('Bank', 100000);
        $rent = $this->category('Rent');
        $event = $this->planning()->expect(['kind' => 'expense', 'description' => 'Rent', 'account_id' => $bank->id, 'category_id' => $rent->id, 'amount' => 50000, 'expected_date' => $this->day()]);
        $typed = $this->ops()->expense($bank, 50000, $rent, ['effective_date' => $this->day()]);

        $this->planning()->match($event, $typed);

        $this->assertSame('posted', $event->fresh()->status);
        $this->assertSame(50000, $this->balance($bank));
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_reversing_a_posted_expected_event_reopens_it(): void
    {
        $bank = $this->account('Bank', 0);
        $salary = $this->category('Salary', 'income');
        $event = $this->planning()->expect(['kind' => 'income', 'description' => 'Salary', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => 1000, 'expected_date' => $this->day()]);
        $tx = $this->planning()->post($event);

        $this->ledger()->reverse($tx, 'Bank returned it');

        $this->assertSame('expected', $event->fresh()->status);
        $this->assertSame(0, $this->balance($bank));
    }
}
