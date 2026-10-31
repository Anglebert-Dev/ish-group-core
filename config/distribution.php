<?php

// Self-hosting (docs/self-hosting-build-guide.md). Shipped in every zip.

return [

    // Never put in a customer's zip, never assignable, refused by updates.
    'never_ship' => ['Billing', 'Distribution'],

    // Ed25519 public keys (base64) that module packages must be signed
    // with. A list, so a key can be replaced without breaking older sites.
    // Zips get our keys written in here; locally they come from .env.
    'public_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('DISTRIBUTION_PUBLIC_KEYS', ''))))),

    // Updates on a self-hosted server: back up the database first (never
    // blocks the update), and run module migrations in-process instead of
    // a fresh "php artisan" process (hosts without proc_open fall back to
    // this anyway).
    'backup_before_update' => (bool) env('UPDATES_BACKUP_FIRST', true),
    'update_in_process' => (bool) env('UPDATES_IN_PROCESS', false),

];
