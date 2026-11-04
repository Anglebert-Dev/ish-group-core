<?php

/*
|--------------------------------------------------------------------------
| Injonge Pay
|--------------------------------------------------------------------------
| Defaults only. Fees and the webhook secret are set in Injonge Pay →
| Platform settings (system table, "injongepay_*"); GTBank credentials come
| from the environment and are never editable on screen.
*/

return [
    'name' => 'InjongePay',

    'currency' => 'RWF',

    'gtbank' => [
        'base_url' => env('GTBANK_BASE_URL'),
        'client_id' => env('GTBANK_CLIENT_ID'),
        'client_secret' => env('GTBANK_CLIENT_SECRET'),
        'participant_name' => env('GTBANK_PARTICIPANT_NAME'),
        'ssl_verify' => env('GTBANK_SSL_VERIFY', true),
        'ca_path' => storage_path('key/gtbank-ca.pem'),
        'timeout' => 20,
        'transfer_timeout' => 60,
    ],

    // Percentages, as strings so they are never floats.
    'default_fee_percentage' => '2.00',
    'default_bank_cut_percentage' => '0.00',

    // Merchant codes: '555' + 5 digits for businesses, 900001+ for the platform's own wallets.
    'merchant_code_prefix' => '555',
    'merchant_code_digits' => 5,
    'internal_merchant_code_start' => 900001,

    // The old Injonge Pay database, read once by injongepay:import-legacy. Never written to.
    'legacy' => [
        'driver' => 'pgsql',
        'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
        'port' => env('LEGACY_DB_PORT', '5432'),
        'database' => env('LEGACY_DB_DATABASE'),
        'username' => env('LEGACY_DB_USERNAME'),
        'password' => env('LEGACY_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
        'sslmode' => env('LEGACY_DB_SSLMODE', 'prefer'),
    ],

    // The old app's APP_KEY: only to carry authenticator (2FA) secrets across.
    'legacy_app_key' => env('LEGACY_APP_KEY'),
];
