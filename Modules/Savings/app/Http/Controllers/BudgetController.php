<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Savings\Models\Category;
use Modules\Savings\Services\Budgets;
use Modules\Savings\Services\Periods;
use Modules\Savings\Support\Money;

class BudgetController extends Controller
{
    public function __construct(protected Budgets $budgets, protected Periods $periods)
    {
    }

    public function index(Request $request): View
    {
        $period = $this->periods->for($request->query('date', now()->toDateString()));
        [$start, $end] = $period;

        return view('savings::budgets.index', [
            'period' => $period,
            'label' => $this->periods->label($period),
            'previous' => $this->periods->previous($start)[0]->toDateString(),
            'next' => $this->periods->next($end)[0]->toDateString(),
            'rows' => $this->budgets->summary($start, $end),
            'income' => array_sum($this->budgets->totals('income', $start->toDateString(), $end->toDateString())),
            'currency' => Money::baseCurrency(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['date' => ['required', 'date'], 'limits' => ['array'], 'limits.*' => ['nullable', 'string', 'max:30']]);
        [$start, $end] = $this->periods->for($request->input('date'));
        $currency = Money::baseCurrency();

        try {
            foreach ((array) $request->input('limits', []) as $uuid => $value) {
                $category = Category::query()->where('uuid', $uuid)->where('kind', 'expense')->first();
                if ($category && filled($value)) {
                    $this->budgets->set($category, $start, $end, Money::parse($value, $currency));
                } elseif ($category) {
                    \Modules\Savings\Models\Budget::query()->where('category_id', $category->id)->whereDate('period_start', $start->toDateString())->delete();
                }
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        audit(\App\Enums\ActivityLogAction::Updated, __('savings::savings.limits_saved').' '.$this->periods->label([$start, $end]));

        return redirect()->route('savings.budgets.index', ['date' => $start->toDateString()])->with('success', __('savings::savings.limits_saved'));
    }

    public function copy(Request $request): RedirectResponse
    {
        [$start, $end] = $this->periods->for($request->input('date', now()->toDateString()));
        $count = $this->budgets->copyPrevious($start, $end);

        return back()->with('success', __('savings::savings.copied', ['count' => $count]));
    }
}
