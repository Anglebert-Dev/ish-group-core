<?php

namespace App\Support\Entitlements;

use App\Contracts\Entitlements;
use App\Models\Business\Business;

/**
 * Default when no billing module is installed: no locks, no limits.
 */
class UnlimitedEntitlements implements Entitlements
{
    public function allowsModule(Business $business, string $module): bool
    {
        return true;
    }

    public function limit(Business $business, string $key): ?int
    {
        return null;
    }

    public function upgradeUrl(): ?string
    {
        return null;
    }
}
