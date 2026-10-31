<?php

return [
    'name' => 'Distribution',

    // Ed25519 secret key (base64) that signs module releases and update
    // answers. Only ever in our .env — `php artisan distribution:keygen`.
    'signing_secret' => env('DISTRIBUTION_SIGNING_SECRET'),

    // PHP and Composer used by builds. Builds run inside a web request,
    // whose PATH may include neither: they are also looked for in the usual
    // folders; set full paths here if "Build zip" still can't find them.
    'php' => env('DISTRIBUTION_PHP'),
    'composer' => env('DISTRIBUTION_COMPOSER', 'composer'),

    // Where release zips and builds are kept, on the private "local" disk.
    'disk' => 'local',
    'directory' => 'distribution',
];
