<?php

/*
|--------------------------------------------------------------------------
| Savings (personal finance)
|--------------------------------------------------------------------------
| Defaults only; each business changes them in Savings → Settings
| (BusinessSettings "savings.*").
*/

return [
    'name' => 'Savings',

    'currency' => 'RWF',

    // Minor-unit digits per currency (money is stored as whole minor units).
    'decimals' => [
        'RWF' => 0, 'UGX' => 0, 'BIF' => 0, 'JPY' => 0, 'XAF' => 0, 'XOF' => 0,
        'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'KES' => 2, 'TZS' => 2, 'ZAR' => 2, 'CAD' => 2, 'AED' => 2, 'CNY' => 2,
    ],

    // Budget period: monthly from start_day (28 = 28th → 27th), weekly from a weekday, biweekly from an anchor date.
    'period' => ['type' => 'monthly', 'start_day' => 1, 'weekday' => 1, 'anchor' => '2026-01-05'],

    // Available to spend = balance − open commitments [− goal earmarks] [− minimum balance].
    'available' => ['goal_earmarks' => true, 'minimum_balance' => true],

    'forecast_days' => 60,
    'recurring_horizon_days' => 120,

    'alerts' => [
        'upcoming_bills' => true, 'overdue_obligations' => true, 'late_income' => true,
        'budget_thresholds' => true, 'missed_contributions' => true, 'shortfalls' => true,
    ],
    'upcoming_days' => 3,
];
