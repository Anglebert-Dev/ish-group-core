<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Budgets;
use Modules\Savings\Services\Forecast;
use Modules\Savings\Services\Obligations;
use Modules\Savings\Services\Periods;
use Modules\Savings\Services\Planning;

class OverviewController extends Controller
{
    public function index(Planning $planning, Obligations $obligations, Forecast $forecast, Periods $periods, Budgets $budgets): View
    {
        $accounts = Account::query()->where('status', 'active')->orderByDesc('is_liquid')->orderBy('name')->get();
        $available = $accounts->mapWithKeys(fn (Account $a) => [$a->id => $planning->available($a)]);
        $owed = Obligation::query()->whereNotIn('status', ['written_off'])->get()
            ->map(fn (Obligation $o) => ['o' => $o, 'outstanding' => $obligations->outstanding($o), 'state' => $obligations->state($o)])
            ->filter(fn ($r) => $r['outstanding'] > 0);
        $period = $periods->current();
        $soon = Expected::query()->where('status', 'expected')->whereDate('expected_date', '<=', now()->addDays(30)->toDateString())
            ->with(['account', 'toAccount'])->orderBy('expected_date')->get();
        $projection = $forecast->run($accounts->where('is_liquid', true)->values(), 30);

        return view('savings::overview', [
            'accounts' => $accounts,
            'available' => $available,
            'owed' => $owed,
            'soon' => $soon,
            'shortfalls' => collect($projection)->filter(fn ($r) => $r['shortfall'] !== null),
            'period' => $period,
            'periodLabel' => $periods->label($period),
            'budgetRows' => $budgets->summary(...$period)->filter(fn ($r) => $r['budgeted'] !== null)->sortByDesc('percent')->take(6),
            'recent' => Transaction::query()->with('entries.account')->latest('effective_date')->latest('id')->limit(8)->get(),
            'late' => $planning->late()->count(),
        ]);
    }
}
