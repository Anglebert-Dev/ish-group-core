<?php

return [
    'name' => 'Distribution',

    // Ed25519 secret key (base64) that signs module releases and update
    // answers. Only ever in our .env — `php artisan distribution:keygen`.
    'signing_secret' => env('DISTRIBUTION_SIGNING_SECRET'),

    // Composer used by builds. Builds run inside a web request, whose PATH
    // may not include it: set the full path here if "Build zip" says so.
    'composer' => env('DISTRIBUTION_COMPOSER', 'composer'),

    // Where release zips and builds are kept, on the private "local" disk.
    'disk' => 'local',
    'directory' => 'distribution',
];
