<?php

return [
    'name' => 'Distribution',

    // Ed25519 secret key (base64) that signs module releases and update
    // answers. Only ever in our .env — `php artisan distribution:keygen`.
    'signing_secret' => env('DISTRIBUTION_SIGNING_SECRET'),

    // Where release zips and builds are kept, on the private "local" disk.
    'disk' => 'local',
    'directory' => 'distribution',
];
