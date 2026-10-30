<?php

namespace Modules\Savings\Services;

use App\Libraries\ListExport;
use Illuminate\Support\Carbon;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Entry;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Line;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * Reports read the ledger (lines and entries), so they always agree with it,
 * including after corrections (reversals net out) (AT-20).
 */
class Reports
{
    public const KEYS = ['spending', 'income', 'budgets', 'cashflow', 'transfers', 'balances', 'obligations', 'goals', 'recurring'];

    public function __construct(
        protected Budgets $budgets,
        protected Periods $periods,
        protected Balances $balances,
        protected Obligations $obligations,
        protected Planning $planning,
    ) {
    }

    /**
     * @return array{table: ListExport, totals: array<string, string>}
     */
    public function run(string $key, string $from, string $to): array
    {
        $c = Money::baseCurrency();

        return match ($key) {
            'spending', 'income' => $this->byCategory($key, $from, $to, $c),
            'budgets' => $this->budgetReport($to, $c),
            'cashflow' => $this->cashflow($from, $to, $c),
            'transfers' => $this->transfers($from, $to),
            'balances' => $this->balanceReport($to),
            'obligations' => $this->obligationReport(),
            'goals' => $this->goalReport(),
            'recurring' => $this->recurringReport(),
        };
    }

    private function byCategory(string $kind, string $from, string $to, string $c): array
    {
        $totals = $this->budgets->totals($kind === 'income' ? 'income' : 'expense', $from, $to);
        $categories = Category::query()->withTrashed()->with('parent')->get()->keyBy('id');
        $rows = collect($totals)->filter()->sortDesc()
            ->map(fn ($amount, $id) => [$categories[$id]?->fullName() ?? '—', Money::format($amount, $c)])->values()->all();

        return [
            'table' => ListExport::make(__('savings::savings.report_'.$kind), 'savings-'.$kind, [__('savings::savings.category'), __('savings::savings.amount')], $rows),
            'totals' => [__('savings::savings.total') => Money::format(array_sum($totals), $c)],
        ];
    }

    private function budgetReport(string $date, string $c): array
    {
        [$start, $end] = $this->periods->for($date);
        $rows = $this->budgets->summary($start, $end)->filter(fn ($r) => $r['budgeted'] !== null || $r['spent'] !== 0);

        return [
            'table' => ListExport::make(__('savings::savings.report_budgets').' '.$this->periods->label([$start, $end]), 'savings-budgets', [
                __('savings::savings.category'), __('savings::savings.budgeted'), __('savings::savings.spent'), __('savings::savings.remaining'), __('savings::savings.projected'),
            ], $rows->map(fn ($r) => [
                $r['category']->fullName(), $r['budgeted'] !== null ? Money::format($r['budgeted'], $c) : '—', Money::format($r['spent'], $c),
                $r['remaining'] !== null ? Money::format($r['remaining'], $c) : '—', Money::format($r['projected'], $c),
            ])->values()->all()),
            'totals' => [
                __('savings::savings.budgeted') => Money::format((int) $rows->sum('budgeted'), $c),
                __('savings::savings.spent') => Money::format((int) $rows->sum('spent'), $c),
            ],
        ];
    }

    /**
     * Money in and out per month. Income and spending exclude transfers;
     * repayments and borrowing are shown on their own rows (not income).
     */
    private function cashflow(string $from, string $to, string $c): array
    {
        $lines = Line::query()->whereDate('effective_date', '>=', $from)->whereDate('effective_date', '<=', $to)->get(['nature', 'amount', 'effective_date']);
        $months = $lines->groupBy(fn ($l) => $l->effective_date->format('Y-m'))->sortKeys();
        $sign = fn ($l) => in_array($l->nature, Ledger::IN, true) ? $l->amount : -$l->amount;

        $rows = $months->map(fn ($group, $month) => [
            $month,
            Money::format((int) $group->whereIn('nature', ['income'])->sum('amount') - (int) $group->where('nature', 'income_refund')->sum('amount'), $c),
            Money::format((int) $group->where('nature', 'expense')->sum('amount') - (int) $group->where('nature', 'expense_refund')->sum('amount'), $c),
            Money::format((int) $group->whereIn('nature', ['receivable_down', 'payable_up', 'receivable_up', 'payable_down'])->sum($sign), $c),
            Money::format((int) $group->sum($sign), $c),
        ])->values()->all();

        return [
            'table' => ListExport::make(__('savings::savings.report_cashflow'), 'savings-cashflow', [
                __('savings::savings.month'), __('savings::savings.income'), __('savings::savings.spending'), __('savings::savings.debts_net'), __('savings::savings.net'),
            ], $rows),
            'totals' => [__('savings::savings.net') => Money::format((int) $lines->sum($sign), $c)],
        ];
    }

    private function transfers(string $from, string $to): array
    {
        $txs = Transaction::query()->where('type', 'transfer')->whereNull('reverses_id')->whereNull('reversed_at')
            ->whereDate('effective_date', '>=', $from)->whereDate('effective_date', '<=', $to)->with('entries.account')->orderBy('effective_date')->get();

        return [
            'table' => ListExport::make(__('savings::savings.report_transfers'), 'savings-transfers', [
                __('savings::savings.date'), __('savings::savings.from'), __('savings::savings.to'), __('savings::savings.amount'), __('savings::savings.description'),
            ], $txs->map(fn (Transaction $t) => [
                $t->effective_date->format('Y-m-d'),
                $t->entries->firstWhere('amount', '<', 0)?->account?->name ?? '—',
                $t->entries->firstWhere('amount', '>', 0)?->account?->name ?? '—',
                Money::format($t->amount, $t->currency),
                $t->description,
            ])->all()),
            'totals' => [__('savings::savings.count') => (string) $txs->count()],
        ];
    }

    private function balanceReport(string $asOf): array
    {
        $accounts = Account::query()->orderBy('name')->get();
        $balances = $this->balances->many($accounts, $asOf);

        return [
            'table' => ListExport::make(__('savings::savings.report_balances').' '.$asOf, 'savings-balances', [
                __('savings::savings.account'), __('savings::savings.type'), __('savings::savings.balance'),
            ], $accounts->map(fn (Account $a) => [$a->name, __('savings::savings.account_'.$a->type), Money::format($balances[$a->id], $a->currency)])->all()),
            'totals' => collect($accounts->groupBy('currency'))->mapWithKeys(fn ($group, $cur) => [
                $cur => Money::format((int) $group->sum(fn ($a) => $balances[$a->id]), $cur),
            ])->all(),
        ];
    }

    private function obligationReport(): array
    {
        $rows = Obligation::query()->orderBy('direction')->get()->map(fn (Obligation $o) => [
            __('savings::savings.direction_'.$o->direction), $o->counterparty ?: $o->description,
            Money::format($this->obligations->original($o), $o->currency), Money::format($this->obligations->outstanding($o), $o->currency),
            __('savings::savings.state_'.$this->obligations->state($o)), $o->due_date?->format('Y-m-d') ?? '—',
        ]);

        return [
            'table' => ListExport::make(__('savings::savings.report_obligations'), 'savings-obligations', [
                __('savings::savings.direction'), __('savings::savings.counterparty'), __('savings::savings.original'), __('savings::savings.outstanding'), __('body.status'), __('savings::savings.due_date'),
            ], $rows->all()),
            'totals' => [__('savings::savings.count') => (string) $rows->count()],
        ];
    }

    private function goalReport(): array
    {
        $rows = Goal::query()->orderBy('name')->get()->map(fn (Goal $g) => [
            $g->name, Money::format($g->target_amount, $g->currency), Money::format($this->planning->earmarked($g), $g->currency),
            Money::format($this->planning->funded($g), $g->currency), $g->target_date?->format('Y-m-d') ?? '—', (string) $this->planning->missedContributions($g),
        ]);

        return [
            'table' => ListExport::make(__('savings::savings.report_goals'), 'savings-goals', [
                __('savings::savings.goal'), __('savings::savings.target'), __('savings::savings.earmarked'), __('savings::savings.funded'), __('savings::savings.target_date'), __('savings::savings.missed'),
            ], $rows->all()),
            'totals' => [__('savings::savings.count') => (string) $rows->count()],
        ];
    }

    private function recurringReport(): array
    {
        $rows = RecurringRule::query()->with('account')->orderBy('description')->get()->map(fn (RecurringRule $r) => [
            $r->description, __('savings::savings.kind_'.$r->kind), Money::format($r->amount, $r->account?->currency),
            __('savings::savings.every', ['n' => $r->interval, 'unit' => __('savings::savings.freq_'.$r->frequency)]),
            $r->is_active ? __('savings::savings.active') : __('savings::savings.inactive'),
        ]);

        return [
            'table' => ListExport::make(__('savings::savings.report_recurring'), 'savings-recurring', [
                __('savings::savings.description'), __('savings::savings.kind'), __('savings::savings.amount'), __('savings::savings.frequency'), __('body.status'),
            ], $rows->all()),
            'totals' => [__('savings::savings.count') => (string) $rows->count()],
        ];
    }
}
