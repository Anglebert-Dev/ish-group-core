<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licence Eligibility Matrix
    |--------------------------------------------------------------------------
    |
    | For each driver licence category (key), the list of vehicle "required
    | licence category" values a holder of that licence is permitted to
    | drive. This is deliberately NOT a simple equality check — a holder of
    | a higher/broader category commonly also covers a lower one (e.g. a
    | bus (D) licence typically also covers a car (B)).
    |
    | Adjust this matrix to match actual local licensing law — it's the
    | single source of truth LicenseEligibilityService reads from.
    |
    */

    'eligibility' => [
        'B' => ['B'],
        'B1' => ['B1', 'B'],
        'C' => ['C', 'B'],
        'D' => ['D', 'B'],
        'D1' => ['D1', 'B'],
        'E' => ['E'],
        'F' => ['F'],
    ],

];
