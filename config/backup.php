<?php

return [
    // Where backup archives are kept (local disk, private).
    'directory' => 'backups',

    // Database dump tools (full paths if they are not on PATH).
    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

    // Folders under storage/app included in the archive (uploads, proofs, logos).
    'include' => ['private', 'public'],
];
