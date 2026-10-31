<?php

namespace Modules\Distribution\Support;

use App\Models\System\System;
use Illuminate\Database\Eloquent\Model;

/**
 * Distribution records have no business; their audit entries belong to the
 * signed-in superadmin's business (or the platform's own, from the CLI).
 */
final class PlatformAudit
{
    public static function log(string $action, string $description, ?Model $subject = null, array $details = []): void
    {
        $businessId = auth('staff')->user()?->business_id ?? ((int) System::getProperty('primary_business_id') ?: null);

        audit($action, $description, $subject, $details, $businessId);
    }
}
