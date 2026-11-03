<?php

return [
    'name' => 'InjongePayConnect',

    // Contracts integrators can be given. Each is a class that owns its routes and answer shapes.
    'profiles' => [
        'pos-ebm-v1' => \Modules\InjongePayConnect\Profiles\PosEbmV1::class,
    ],

    'token_ttl_seconds' => 3600,

    // Requests per minute per connection, unless the connection says otherwise.
    'rate_limit' => 120,

    // Partners have always received times in Kigali time (+02:00).
    'timezone' => 'Africa/Kigali',

    // The old single-key EBM lookup (/api/ebm/payments/lookup), kept for whoever still uses it.
    'legacy_ebm_key' => env('EBM_API_KEY'),
];
