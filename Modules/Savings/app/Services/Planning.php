<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\GoalAllocation;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * Expected events, recurring rules, commitments and goals. All plans:
 * they never change a balance until something is actually posted.
 */
class Planning
{
    public function __construct(
        protected Operations $operations,
        protected Obligations $obligations,
        protected Balances $balances,
    ) {
    }

    // ---- Expected events -------------------------------------------------

    public function expect(array $data): Expected
    {
        $event = new Expected($data + ['status' => 'expected']);
        $event->save();

        return $event;
    }

    /**
     * Expected income in instalments (e.g. salary 1,000,000 as 450,000 now and 550,000 later).
     *
     * @param  list<array{date: string, amount: int}>  $instalments
     */
    public function expectInstalments(Account $account, Category $category, string $label, array $instalments, string $confidence = 'confirmed'): array
    {
        return DB::transaction(fn () => array_map(fn ($i) => $this->expect([
            'kind' => 'income', 'description' => $label, 'group_label' => $label, 'account_id' => $account->id,
            'category_id' => $category->id, 'amount' => $i['amount'], 'expected_date' => $i['date'], 'confidence' => $confidence,
        ]), $instalments));
    }

    /**
     * It happened: post it once (same key every time, so a retry can't post twice).
     */
    public function post(Expected $event, ?int $amount = null, ?string $date = null, ?Account $account = null): Transaction
    {
        if (! $event->isOpen()) {
            if ($event->transaction) {
                return $event->transaction;
            }
            throw new LedgerException(__('savings::savings.expected_closed'));
        }

        $amount ??= $event->amount;
        $account ??= $event->account;
        $meta = [
            'effective_date' => $date ?? now()->toDateString(),
            'description' => $event->description,
            'idempotency_key' => 'expected:'.$event->uuid,
            'source' => 'expected',
        ];

        return DB::transaction(function () use ($event, $amount, $account, $meta) {
            $category = $event->category_id ? Category::query()->find($event->category_id) : null;
            $tx = match ($event->kind) {
                'income' => $this->operations->income($account, $amount, $category ?? throw new LedgerException(__('savings::savings.category_required', ['kind' => 'income'])), $meta),
                'expense' => $this->operations->expense($account, $amount, $category ?? throw new LedgerException(__('savings::savings.category_required', ['kind' => 'expense'])), $meta),
                'transfer', 'contribution' => $this->contributionOrTransfer($event, $account, $amount, $meta),
                'repayment_in', 'repayment_out' => $this->obligations->repay(Obligation::query()->findOrFail($event->obligation_id), $account, $amount, $meta),
            };

            $event->forceFill(['status' => 'posted', 'transaction_id' => $tx->id])->save();

            return $tx;
        });
    }

    /**
     * It was already recorded (typed in or imported): link instead of posting again.
     */
    public function match(Expected $event, Transaction $tx): void
    {
        if (! $event->isOpen() || Expected::query()->where('transaction_id', $tx->id)->exists()) {
            throw new LedgerException(__('savings::savings.expected_closed'));
        }

        $event->forceFill(['status' => 'posted', 'transaction_id' => $tx->id])->save();
    }

    public function skip(Expected $event, string $reason): void
    {
        if (! $event->isOpen()) {
            throw new LedgerException(__('savings::savings.expected_closed'));
        }

        $event->forceFill(['status' => 'skipped', 'notes' => trim(($event->notes ? $event->notes."\n" : '').$reason)])->save();
    }

    /**
     * Expected events that already passed without happening.
     */
    public function late()
    {
        return Expected::query()->where('status', 'expected')->whereDate('expected_date', '<', now()->toDateString())->orderBy('expected_date');
    }

    // ---- Recurring rules ---------------------------------------------------

    public function saveRule(RecurringRule $rule, array $data): RecurringRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $rule->fill($data)->save();
            $this->regenerate($rule);

            return $rule;
        });
    }

    /**
     * Future, not-yet-posted occurrences follow the rule; posted and past ones never change (AT-18).
     */
    public function regenerate(RecurringRule $rule): int
    {
        Expected::query()->where('recurring_rule_id', $rule->id)->where('status', 'expected')
            ->whereDate('expected_date', '>=', now()->toDateString())->forceDelete();

        if (! $rule->is_active || $rule->trashed()) {
            return 0;
        }

        $horizon = now()->addDays((int) config('savings.recurring_horizon_days', 120))->startOfDay();
        $created = 0;

        foreach ($this->occurrences($rule, $horizon) as $date) {
            if ($date->lt(now()->startOfDay())) {
                continue;
            }
            $exists = Expected::query()->withTrashed()->where('recurring_rule_id', $rule->id)->whereDate('expected_date', $date->toDateString())->exists();
            if (! $exists) {
                $this->expect([
                    'kind' => $rule->kind, 'description' => $rule->description, 'account_id' => $rule->account_id,
                    'to_account_id' => $rule->to_account_id, 'category_id' => $rule->category_id, 'goal_id' => $rule->goal_id,
                    'recurring_rule_id' => $rule->id, 'amount' => $rule->amount, 'expected_date' => $date->toDateString(),
                    'confidence' => $rule->is_variable ? 'expected' : $rule->confidence,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return list<Carbon>
     */
    public function occurrences(RecurringRule $rule, Carbon $until): array
    {
        $dates = [];
        $start = $rule->start_date->copy()->startOfDay();
        $day = $start->day;
        $end = $rule->end_date ? min($rule->end_date->copy(), $until) : $until;
        $step = max(1, $rule->interval);

        for ($i = 0; $i < 1000; $i++) {
            $date = match ($rule->frequency) {
                'day' => $start->copy()->addDays($i * $step),
                'week' => $start->copy()->addWeeks($i * $step),
                'year' => $start->copy()->addYearsNoOverflow($i * $step),
                default => $start->copy()->startOfMonth()->addMonthsNoOverflow($i * $step)->day(min($day, $start->copy()->startOfMonth()->addMonthsNoOverflow($i * $step)->daysInMonth)),
            };
            if ($date->gt($end)) {
                break;
            }
            $dates[] = $date;
        }

        return $dates;
    }

    // ---- Commitments ---------------------------------------------------------

    public function commit(Account $account, string $description, int $amount, ?string $dueDate = null): Commitment
    {
        if ($amount <= 0) {
            throw new LedgerException(__('savings::savings.amount_invalid'));
        }

        $commitment = new Commitment(['account_id' => $account->id, 'description' => $description, 'amount' => $amount, 'due_date' => $dueDate, 'status' => 'open']);
        $commitment->save();

        return $commitment;
    }

    /**
     * Pay what was committed: posts the expense and closes the commitment.
     */
    public function settleCommitment(Commitment $commitment, Category $category, array $meta = []): Transaction
    {
        if ($commitment->status !== 'open') {
            throw new LedgerException(__('savings::savings.commitment_closed'));
        }

        return DB::transaction(function () use ($commitment, $category, $meta) {
            $tx = $this->operations->expense($commitment->account, $commitment->amount, $category, $meta + [
                'description' => $commitment->description, 'idempotency_key' => 'commitment:'.$commitment->uuid,
            ]);
            $commitment->forceFill(['status' => 'settled', 'transaction_id' => $tx->id])->save();

            return $tx;
        });
    }

    public function cancelCommitment(Commitment $commitment): void
    {
        $commitment->forceFill(['status' => 'cancelled'])->save();
    }

    // ---- Goals ----------------------------------------------------------------

    /**
     * Earmark money for a goal (a plan; no balance changes, BR-05).
     */
    public function allocate(Goal $goal, int $amount, ?string $note = null): GoalAllocation
    {
        if ($amount === 0) {
            throw new LedgerException(__('savings::savings.amount_invalid'));
        }

        if ($this->earmarked($goal) + $amount < 0) {
            throw new LedgerException(__('savings::savings.allocation_below_zero'));
        }

        return $this->allocation($goal, 'allocation', $amount, null, null, $note);
    }

    /**
     * Move earmarked money from one goal to another, both sides recorded.
     */
    public function reassign(Goal $from, Goal $to, int $amount, string $note): void
    {
        if ($amount <= 0 || $amount > $this->earmarked($from)) {
            throw new LedgerException(__('savings::savings.allocation_below_zero'));
        }

        DB::transaction(function () use ($from, $to, $amount, $note) {
            $this->allocation($from, 'allocation', -$amount, null, $to, $note);
            $this->allocation($to, 'allocation', $amount, null, $from, $note);
        });
    }

    /**
     * Actually move money into the goal's account: a posted transfer counted as a contribution.
     */
    public function contribute(Goal $goal, Account $from, int $amount, array $meta = []): Transaction
    {
        if (! $goal->account) {
            throw new LedgerException(__('savings::savings.goal_no_account'));
        }

        return DB::transaction(function () use ($goal, $from, $amount, $meta) {
            $tx = $this->operations->transfer($from, $goal->account, $amount, $meta + ['description' => __('savings::savings.contribution_to', ['name' => $goal->name])]);
            $this->allocation($goal, 'contribution', $amount, $tx);

            return $tx;
        });
    }

    public function earmarked(Goal $goal): int
    {
        return (int) GoalAllocation::query()->where('goal_id', $goal->id)->where('kind', 'allocation')->sum('amount');
    }

    public function funded(Goal $goal): int
    {
        return (int) GoalAllocation::query()->where('goal_id', $goal->id)->where('kind', 'contribution')->sum('amount');
    }

    /**
     * Planned contributions that passed without happening.
     */
    public function missedContributions(Goal $goal): int
    {
        return Expected::query()->where('goal_id', $goal->id)->where('kind', 'contribution')->where('status', 'expected')
            ->whereDate('expected_date', '<', now()->toDateString())->count();
    }

    // ---- Available to spend ----------------------------------------------------------

    /**
     * Balance − open commitments [− goal earmarks held here] [− minimum balance] (BR-06).
     *
     * @return array{balance: int, commitments: int, earmarks: int, minimum: int, available: int}
     */
    public function available(Account $account): array
    {
        $policy = array_merge(config('savings.available'), (array) app(\App\Support\Settings\BusinessSettings::class)->get('savings.available', []));
        $balance = $this->balances->balance($account);
        $commitments = (int) Commitment::query()->where('account_id', $account->id)->where('status', 'open')->sum('amount');
        $earmarks = $policy['goal_earmarks']
            ? (int) GoalAllocation::query()->where('kind', 'allocation')->whereIn('goal_id', Goal::query()->where('account_id', $account->id)->where('status', 'active')->select('id'))->sum('amount')
            : 0;
        $minimum = $policy['minimum_balance'] ? (int) ($account->minimum_balance ?? 0) : 0;

        return [
            'balance' => $balance,
            'commitments' => $commitments,
            'earmarks' => $earmarks,
            'minimum' => $minimum,
            'available' => $balance - $commitments - $earmarks - $minimum,
        ];
    }

    private function contributionOrTransfer(Expected $event, Account $account, int $amount, array $meta): Transaction
    {
        if ($event->kind === 'contribution' && $event->goal_id) {
            return $this->contribute(Goal::query()->findOrFail($event->goal_id), $account, $amount, $meta);
        }

        return $this->operations->transfer($account, Account::query()->findOrFail($event->to_account_id), $amount, $meta);
    }

    private function allocation(Goal $goal, string $kind, int $amount, ?Transaction $tx = null, ?Goal $related = null, ?string $note = null): GoalAllocation
    {
        $allocation = new GoalAllocation;
        $allocation->forceFill([
            'business_id' => $goal->business_id, 'goal_id' => $goal->id, 'kind' => $kind, 'amount' => $amount,
            'transaction_id' => $tx?->id, 'related_goal_id' => $related?->id, 'effective_date' => $tx?->effective_date ?? now()->toDateString(),
            'note' => $note, 'user_id' => auth()->id(),
        ])->save();

        audit(\App\Enums\ActivityLogAction::Updated, __('savings::savings.audit_goal', [
            'kind' => __('savings::savings.alloc_'.$kind), 'amount' => Money::format($amount, $goal->currency), 'name' => $goal->name,
        ]), $goal, ['note' => $note], $goal->business_id);

        return $allocation;
    }
}
