<?php

namespace Modules\Billing\Support;

use App\Libraries\ListExport;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Subscription;

/**
 * PDF / Excel exports of the superadmin billing lists (same filters as the
 * screens).
 */
class BillingExports
{
    public static function subscriptions(Builder $query): ListExport
    {
        $rows = $query->with('business')->limit(5000)->get()->map(fn (Subscription $s) => [
            $s->business?->name ?? '—',
            $s->name(),
            $s->status->label(),
            Money::format($s->price()).' / '.$s->interval()->label($s->intervalCount()),
            $s->current_period_end?->format('Y-m-d') ?? '—',
            $s->created_at?->format('Y-m-d'),
        ])->all();

        return ListExport::make(__('billing::billing.export_subscriptions'), 'subscriptions', [
            __('body.business'), __('billing::billing.package'), __('body.status'),
            __('billing::billing.price'), __('billing::billing.paid_until'), __('body.created'),
        ], $rows);
    }

    public static function invoices(Builder $query): ListExport
    {
        $rows = $query->with('business')->limit(5000)->get()->map(fn (Invoice $i) => [
            $i->number,
            $i->business?->name ?? '—',
            $i->issued_at?->format('Y-m-d'),
            $i->due_at?->format('Y-m-d'),
            Money::format($i->total, $i->currency),
            Money::format($i->amount_paid, $i->currency),
            $i->isOverdue() ? __('billing::billing.overdue') : $i->status->label(),
        ])->all();

        return ListExport::make(__('billing::billing.export_invoices'), 'invoices', [
            __('billing::billing.invoice'), __('body.business'), __('billing::billing.issued'), __('billing::billing.due'),
            __('billing::billing.total'), __('billing::billing.amount_paid'), __('body.status'),
        ], $rows);
    }

    public static function payments(Builder $query): ListExport
    {
        $rows = $query->with(['business', 'invoice', 'reviewer'])->limit(5000)->get()->map(fn (Payment $p) => [
            $p->business?->name ?? '—',
            $p->invoice?->number ?? '—',
            Money::format($p->amount, $p->invoice?->currency),
            $p->method->label(),
            $p->reference ?? '—',
            $p->paid_on?->format('Y-m-d'),
            $p->status->label(),
            $p->reviewer?->name ?? '—',
        ])->all();

        return ListExport::make(__('billing::billing.export_payments'), 'payments', [
            __('body.business'), __('billing::billing.invoice'), __('body.amount'), __('billing::billing.method'),
            __('billing::billing.reference'), __('billing::billing.paid_on'), __('body.status'), __('billing::billing.reviewed_by', ['name' => '']),
        ], $rows);
    }
}
