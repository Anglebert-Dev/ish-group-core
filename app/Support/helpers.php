<?php

use App\Enums\ActivityLogAction;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('money')) {
    /**
     * An amount in the current business's currency: money(15000) → "RWF 15,000".
     * Currency is the "business.currency" setting (default config app.currency).
     */
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency ??= (string) (app(\App\Support\Tenancy\CurrentBusiness::class)->id()
            ? app(\App\Support\Settings\BusinessSettings::class)->get('business.currency', config('app.currency', 'RWF'))
            : config('app.currency', 'RWF'));
        $value = (float) $amount;
        $decimals = abs($value - round($value)) < 0.005 ? 0 : 2;

        return $currency.' '.number_format($value, $decimals);
    }
}

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
