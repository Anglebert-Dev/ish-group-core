<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Savings\Models\Budget;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Line;

/**
 * Budgets are plans (BR-05): a limit per category per period. Spending comes
 * from expense lines only, so transfers, repayments and savings moves never count.
 */
class Budgets
{
    public function __construct(protected Periods $periods)
    {
    }

    /**
     * Net amount per category in a date range: [category_id => amount].
     * Expense: expense − expense refunds. Income: income − income refunds. Reversals net out.
     */
    public function totals(string $kind, string $from, string $to): array
    {
        [$plus, $minus] = $kind === 'income' ? ['income', 'income_refund'] : ['expense', 'expense_refund'];

        return Line::query()
            ->whereIn('nature', [$plus, $minus])
            ->whereDate('effective_date', '>=', $from)->whereDate('effective_date', '<=', $to)
            ->selectRaw("category_id, SUM(CASE WHEN nature = ? THEN amount ELSE -amount END) as total", [$plus])
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Summary rows for a period: budgeted, spent, remaining, over, projected (spent pace to period end).
     *
     * @return Collection<int, array{category: Category, budgeted: ?int, spent: int, remaining: ?int, over: int, projected: int, percent: ?int}>
     */
    public function summary(Carbon $start, Carbon $end): Collection
    {
        $spentByCategory = $this->totals('expense', $start->toDateString(), $end->toDateString());
        $budgets = Budget::query()->where('period_start', $start->toDateString())->pluck('amount', 'category_id');
        $categories = Category::query()->where('kind', 'expense')->with('parent')->orderBy('name')->get();

        $days = $start->diffInDays($end) + 1;
        $elapsed = now()->lt($start) ? 0 : (now()->gt($end) ? $days : $start->diffInDays(now()->startOfDay()) + 1);

        return $categories
            ->map(function (Category $category) use ($spentByCategory, $budgets, $categories, $days, $elapsed) {
                // A parent's spending includes its sub-categories.
                $ids = $categories->where('parent_id', $category->id)->pluck('id')->push($category->id);
                $spent = $ids->sum(fn ($id) => $spentByCategory[$id] ?? 0);
                $budgeted = isset($budgets[$category->id]) ? (int) $budgets[$category->id] : null;
                $projected = $elapsed > 0 ? intdiv($spent * $days, $elapsed) : 0;

                return [
                    'category' => $category,
                    'budgeted' => $budgeted,
                    'spent' => $spent,
                    'remaining' => $budgeted !== null ? $budgeted - $spent : null,
                    'over' => $budgeted !== null ? max(0, $spent - $budgeted) : 0,
                    'projected' => $projected,
                    'percent' => $budgeted ? intdiv($spent * 100, $budgeted) : null,
                ];
            })
            ->filter(fn ($row) => $row['budgeted'] !== null || $row['spent'] !== 0 || ! $row['category']->is_archived)
            ->values();
    }

    public function set(Category $category, Carbon $start, Carbon $end, int $amount): void
    {
        $budget = Budget::query()->firstOrNew(['category_id' => $category->id, 'period_start' => $start->toDateString()]);
        $budget->business_id = $category->business_id;
        $budget->period_end = $end->toDateString();
        $budget->amount = $amount;
        $budget->save();
    }

    /**
     * Copy the previous period's limits into this one (only categories without a limit yet).
     */
    public function copyPrevious(Carbon $start, Carbon $end): int
    {
        [$prevStart] = $this->periods->previous($start);
        $copied = 0;

        foreach (Budget::query()->where('period_start', $prevStart->toDateString())->get() as $old) {
            if (! Budget::query()->where('category_id', $old->category_id)->where('period_start', $start->toDateString())->exists()) {
                $budget = new Budget(['category_id' => $old->category_id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'amount' => $old->amount]);
                $budget->business_id = $old->business_id;
                $budget->save();
                $copied++;
            }
        }

        return $copied;
    }
}
