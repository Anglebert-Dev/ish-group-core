<?php

namespace App\Contracts;

use App\Models\Business\Business;

/**
 * What a business is allowed to use. The core asks; a billing module
 * answers (packages/subscriptions). Without one, everything is allowed —
 * see App\Support\Entitlements\UnlimitedEntitlements.
 */
interface Entitlements
{
    /**
     * May this business use the given code module right now?
     */
    public function allowsModule(Business $business, string $module): bool;

    /**
     * Limit for a countable resource, e.g. "users". Null = unlimited.
     */
    public function limit(Business $business, string $key): ?int;

    /**
     * Where to send a business that hit a module lock or a limit
     * (e.g. its billing page). Null = no such page.
     */
    public function upgradeUrl(): ?string;
}
