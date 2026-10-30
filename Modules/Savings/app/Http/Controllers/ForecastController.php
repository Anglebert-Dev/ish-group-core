<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Expected;
use Modules\Savings\Services\Forecast;
use Modules\Savings\Support\Money;

class ForecastController extends Controller
{
    public function index(Request $request, Forecast $forecast): View
    {
        $days = max(7, min(365, (int) $request->query('days', config('savings.forecast_days', 60))));
        $accounts = Account::query()->where('status', 'active')->orderByDesc('is_liquid')->orderBy('name')->get();
        $selected = $request->filled('account') ? $accounts->where('uuid', $request->query('account'))->values() : $accounts->where('is_liquid', true)->values();

        // What-if: only in this request, never saved.
        $overrides = [];
        foreach ((array) $request->query('w', []) as $uuid => $change) {
            $event = Expected::query()->where('uuid', $uuid)->with('account')->first();
            if (! $event) {
                continue;
            }
            $overrides[$uuid] = array_filter([
                'date' => filled($change['date'] ?? null) ? $change['date'] : null,
                'amount' => filled($change['amount'] ?? null) ? rescue(fn () => Money::parse($change['amount'], $event->account->currency), null, false) : null,
                'exclude' => ! empty($change['exclude']) ?: null,
            ], fn ($v) => $v !== null);
        }

        return view('savings::forecast.index', [
            'rows' => $forecast->run($selected, $days, array_filter($overrides)),
            'accounts' => $accounts,
            'days' => $days,
            'account' => $request->query('account'),
            'whatIf' => $request->query('w', []),
        ]);
    }
}
