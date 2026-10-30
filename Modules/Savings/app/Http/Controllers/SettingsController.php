<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Services\Periods;
use Modules\Savings\Support\Money;
use Modules\Savings\Support\Settings;

class SettingsController extends Controller
{
    public function edit(Periods $periods): View
    {
        return view('savings::settings', [
            'currency' => Money::baseCurrency(),
            'period' => $periods->rule(),
            'available' => array_merge(config('savings.available'), (array) Settings::get('available', [])),
            'alerts' => array_merge(config('savings.alerts'), (array) Settings::get('alerts', [])),
            'currencies' => array_keys(config('savings.decimals')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'period_type' => ['required', Rule::in(['monthly', 'weekly', 'biweekly'])],
            'start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'anchor' => ['required', 'date'],
        ]);

        // Existing budgets keep their stored period dates (BR-12); only new periods follow the rule.
        // Your own settings: other people's finances keep theirs.
        Settings::set('currency', strtoupper($data['currency']));
        Settings::set('period', ['type' => $data['period_type'], 'start_day' => (int) $data['start_day'], 'weekday' => (int) $data['weekday'], 'anchor' => $data['anchor']]);
        Settings::set('available', ['goal_earmarks' => $request->boolean('goal_earmarks'), 'minimum_balance' => $request->boolean('minimum_balance')]);
        Settings::set('alerts', collect(array_keys(config('savings.alerts')))->mapWithKeys(fn ($k) => [$k => $request->boolean('alert_'.$k)])->all());

        return back()->with('success', __('savings::savings.settings_saved'));
    }
}
