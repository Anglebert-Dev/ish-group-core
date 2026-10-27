<?php

use App\Enums\ActivityLogAction;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('audit')) {
    /**
     * Write an audit-log entry for anything that is not a plain record change.
     *
     *   audit('sale_completed', "Sale #{$sale->number} completed", $sale, ['total' => $sale->total]);
     *   audit(ActivityLogAction::Exported, 'Exported stock list');
     *
     * The business is taken from $subject (its business_id) or the signed-in
     * user, so each business only ever sees its own entries. Module actions
     * get a label from "<module>::audit.<action>" when that translation exists.
     */
    function audit(ActivityLogAction|string $action, string $description, ?Model $subject = null, array $details = [], ?int $businessId = null): void
    {
        app(ActivityLogService::class)->log($action, $description, $subject, $details, $businessId);
    }
}
