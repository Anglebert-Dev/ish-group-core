<?php

use App\Enums\ActivityLogAction;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Branding\BrandingService;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;

/*
 * Modules\{Name}\... → Modules/{Name}/app/... without Composer. A module
 * added after the vendor folder was built (an update on a self-hosted
 * server, where Composer can't run) still loads. Prepended so a module
 * swapped in by an update wins over a stale optimized classmap entry.
 */
spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'Modules\\')) {
        return;
    }

    $parts = explode('\\', $class);

    if (count($parts) < 3 || in_array('..', $parts, true)) {
        return;
    }

    [, $module] = $parts;
    $rest = array_slice($parts, 2);

    // Module tests live outside app/ (Modules\X\Tests\... → Modules/X/tests/...).
    $file = $rest[0] === 'Tests'
        ? dirname(__DIR__, 2)."/Modules/{$module}/tests/".implode('/', array_slice($rest, 1)).'.php'
        : dirname(__DIR__, 2)."/Modules/{$module}/app/".implode('/', $rest).'.php';

    if (is_file($file)) {
        require_once $file;
    }
}, prepend: true);

if (! function_exists('money')) {
    /**
     * An amount in the current business's currency: money(15000) → "RWF 15,000".
     * Currency is the "business.currency" setting (default config app.currency).
     */
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency ??= (string) (app(CurrentBusiness::class)->id()
            ? app(BusinessSettings::class)->get('business.currency', config('app.currency', 'RWF'))
            : config('app.currency', 'RWF'));
        $value = (float) $amount;
        $decimals = abs($value - round($value)) < 0.005 ? 0 : 2;
        [$point, $thousands] = number_separators();

        return $currency.' '.number_format($value, $decimals, $point, $thousands);
    }
}

if (! function_exists('number_separators')) {
    /**
     * The business's way of writing numbers (Branding → Business details):
     * [decimal point, thousands separator].
     *
     * @return array{0: string, 1: string}
     */
    function number_separators(): array
    {
        $style = app(CurrentBusiness::class)->id()
            ? app(BusinessSettings::class)->get('branding.number_format')
            : null;

        return BrandingService::NUMBER_FORMATS[$style] ?? ['.', ','];
    }
}

if (! function_exists('short_date_format')) {
    /** The business's short date ("d/m/Y", "m/d/Y", "Y-m-d" or "d.m.Y"), for ->format(). */
    function short_date_format(): string
    {
        $format = app(CurrentBusiness::class)->id()
            ? app(BusinessSettings::class)->get('branding.date_format')
            : null;

        return in_array($format, BrandingService::DATE_FORMATS, true) ? $format : 'd/m/Y';
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
