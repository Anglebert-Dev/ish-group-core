<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Services\Periods;
use Modules\Savings\Support\Money;

class SettingsController extends Controller
{
    public function edit(BusinessSettings $settings, Periods $periods): View
    {
        return view('savings::settings', [
            'currency' => Money::baseCurrency(),
            'period' => $periods->rule(),
            'available' => array_merge(config('savings.available'), (array) $settings->get('savings.available', [])),
            'alerts' => array_merge(config('savings.alerts'), (array) $settings->get('savings.alerts', [])),
            'currencies' => array_keys(config('savings.decimals')),
        ]);
    }

    public function update(Request $request, BusinessSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'period_type' => ['required', Rule::in(['monthly', 'weekly', 'biweekly'])],
            'start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'anchor' => ['required', 'date'],
        ]);

        // Existing budgets keep their stored period dates (BR-12); only new periods follow the rule.
        $settings->set('savings.currency', strtoupper($data['currency']));
        $settings->set('savings.period', ['type' => $data['period_type'], 'start_day' => (int) $data['start_day'], 'weekday' => (int) $data['weekday'], 'anchor' => $data['anchor']]);
        $settings->set('savings.available', ['goal_earmarks' => $request->boolean('goal_earmarks'), 'minimum_balance' => $request->boolean('minimum_balance')]);
        $settings->set('savings.alerts', collect(array_keys(config('savings.alerts')))->mapWithKeys(fn ($k) => [$k => $request->boolean('alert_'.$k)])->all());

        return back()->with('success', __('savings::savings.settings_saved'));
    }
}
