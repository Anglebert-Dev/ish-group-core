<?php

namespace Modules\Billing\Services;

use App\Enums\NotificationType;
use App\Models\Business\Business;
use App\Services\BaseService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\CouponDuration;
use Modules\Billing\Enums\InvoiceReason;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\BillingSettings;
use Modules\Billing\Support\Money;

/**
 * Subscription lifecycle.
 *
 *   subscribe ─► trialing ─┐            (no payment needed during a trial)
 *            ├► active (free package)
 *            └► pending ──► active      (first invoice paid)
 *   active ──► renewal invoice issued `reminder_days` before the period end
 *          ──► period ends unpaid ─► past_due (grace_days, modules still on)
 *                                 ─► suspended (paid modules locked)
 *   paying any open invoice brings it back to active.
 *
 * Upgrades (higher price per day) are invoiced now with a prorated credit
 * and take effect once paid; downgrades wait for the period end.
 */
class SubscriptionService extends BaseService
{
    public function __construct(
        protected InvoiceService $invoices,
        protected CouponService $coupons,
        protected BillingNotifier $notifier,
    ) {
    }

    public function current(Business $business): ?Subscription
    {
        return Subscription::query()
            ->where('business_id', $business->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, SubscriptionStatus::current()))
            ->latest('id')
            ->first();
    }

    /**
     * Start a subscription, or change the plan of the current one.
     *
     * @param  bool  $assignedBySuperadmin  skips "available to this business" checks
     */
    public function subscribe(Business $business, Package $package, ?string $couponCode = null, bool $assignedBySuperadmin = false): array
    {
        if (! $assignedBySuperadmin && ! $package->isAvailableTo($business)) {
            return $this->failure(__('billing::billing.package_unavailable'));
        }

        if ($package->once_per_business && Subscription::withTrashed()
            ->where('business_id', $business->id)->where('package_id', $package->id)->exists()) {
            return $this->failure(__('billing::billing.package_once'));
        }

        $couponResult = $this->coupons->validate($couponCode, $business, $package);
        if (! $couponResult['status']) {
            return $couponResult;
        }
        $coupon = $couponResult['data'];

        $current = $this->current($business);

        if ($current && in_array($current->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return $this->changePlan($current, $package, $coupon);
        }

        return DB::transaction(function () use ($business, $package, $coupon, $current) {
            // A pending or suspended subscription is replaced by the new one.
            if ($current) {
                $this->end($current, __('billing::billing.replaced'));
            }

            $subscription = new Subscription([
                'business_id' => $business->id,
                'package_id' => $package->id,
                'snapshot' => $package->snapshot(),
                'coupon_id' => $coupon?->id,
                'coupon_periods_remaining' => $this->couponPeriods($coupon),
            ]);

            $hadTrial = Subscription::withTrashed()->where('business_id', $business->id)->whereNotNull('trial_ends_at')->exists();

            if ($package->trial_days > 0 && ! $hadTrial) {
                $subscription->status = SubscriptionStatus::Trialing;
                $subscription->trial_ends_at = now()->addDays($package->trial_days);
                $subscription->current_period_start = now();
                $subscription->current_period_end = $subscription->trial_ends_at;
                $subscription->save();
                $message = __('billing::billing.trial_started', ['days' => $package->trial_days]);
            } elseif ($package->isFree()) {
                $subscription->status = SubscriptionStatus::Active;
                $this->startPeriod($subscription, now());
                $subscription->save();
                $message = __('billing::billing.subscribed', ['name' => $package->name]);
            } else {
                $subscription->status = SubscriptionStatus::Pending;
                $subscription->save();
                $this->invoices->issue($business, $subscription, $package, InvoiceReason::NewSubscription, $this->couponForNextInvoice($subscription));
                $this->consumeCouponPeriod($subscription);
                $message = __('billing::billing.awaiting_payment');
            }

            if ($coupon) {
                $this->coupons->redeem($coupon, $business, $subscription->id);
            }

            return $this->success($subscription->fresh(), $message);
        });
    }

    /**
     * What subscribing / switching to $package would cost now (no writes).
     */
    public function preview(Business $business, Package $package, ?Coupon $coupon = null): array
    {
        $current = $this->current($business);
        $hadTrial = Subscription::withTrashed()->where('business_id', $business->id)->whereNotNull('trial_ends_at')->exists();

        $mode = match (true) {
            $current && $current->package_id === $package->id && $current->status !== SubscriptionStatus::Suspended && $current->status !== SubscriptionStatus::Pending => 'same',
            $current && $current->status === SubscriptionStatus::Trialing => 'switch_trial',
            $current && in_array($current->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true) => $this->isUpgrade($current, $package) ? 'upgrade' : 'downgrade',
            $package->trial_days > 0 && ! $hadTrial => 'trial',
            $package->isFree() => 'free',
            default => 'new',
        };

        $draft = in_array($mode, ['upgrade', 'new'], true)
            ? $this->invoices->compose($business, $package, $coupon, $mode === 'upgrade' ? $this->prorationCredit($current) : 0)
            : null;

        return [
            'mode' => $mode,
            'current' => $current,
            'draft' => $draft,
            'effective_at' => $mode === 'downgrade' ? $current?->current_period_end : now(),
        ];
    }

    public function changePlan(Subscription $subscription, Package $package, ?Coupon $coupon = null): array
    {
        if ($subscription->package_id === $package->id) {
            if ($subscription->scheduled_package_id) {
                $subscription->forceFill(['scheduled_package_id' => null])->save();

                return $this->success($subscription, __('billing::billing.change_cancelled'));
            }

            return $this->failure(__('billing::billing.already_on_package'));
        }

        return DB::transaction(function () use ($subscription, $package, $coupon) {
            // During a trial: switch straight away, the trial keeps running.
            if ($subscription->status === SubscriptionStatus::Trialing) {
                $subscription->forceFill([
                    'package_id' => $package->id,
                    'snapshot' => $package->snapshot(),
                    'scheduled_package_id' => null,
                ])->save();
                $this->voidOpen($subscription, InvoiceReason::Renewal);

                return $this->success($subscription, __('billing::billing.switched', ['name' => $package->name]));
            }

            if (! $this->isUpgrade($subscription, $package)) {
                $subscription->forceFill(['scheduled_package_id' => $package->id])->save();
                // A renewal invoice for the old package is re-issued for the new one.
                $this->voidOpen($subscription, InvoiceReason::Renewal);

                return $this->success($subscription, __('billing::billing.downgrade_scheduled', [
                    'name' => $package->name,
                    'date' => $subscription->current_period_end?->format('d M Y'),
                ]));
            }

            $this->voidOpen($subscription, InvoiceReason::Change);
            $invoice = $this->invoices->issue(
                $subscription->business,
                $subscription,
                $package,
                InvoiceReason::Change,
                $coupon,
                $this->prorationCredit($subscription),
            );

            if ($coupon) {
                $subscription->forceFill([
                    'coupon_id' => $coupon->id,
                    'coupon_periods_remaining' => $this->couponPeriods($coupon, consumedOne: true),
                ])->save();
                $this->coupons->redeem($coupon, $subscription->business, $subscription->id);
            }

            return $this->success($invoice, __('billing::billing.upgrade_invoiced', ['name' => $package->name]));
        });
    }

    public function cancel(Subscription $subscription): array
    {
        if ($subscription->status === SubscriptionStatus::Pending || $subscription->status === SubscriptionStatus::Suspended) {
            $this->end($subscription, __('billing::billing.canceled_by_business'));

            return $this->success($subscription, __('billing::billing.canceled_now'));
        }

        $subscription->forceFill([
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
            'scheduled_package_id' => null,
        ])->save();
        $this->voidOpen($subscription, InvoiceReason::Renewal);

        return $this->success($subscription, __('billing::billing.cancel_scheduled', [
            'date' => $subscription->current_period_end?->format('d M Y'),
        ]));
    }

    public function resume(Subscription $subscription): array
    {
        $subscription->forceFill(['cancel_at_period_end' => false, 'canceled_at' => null])->save();

        return $this->success($subscription, __('billing::billing.resumed'));
    }

    /**
     * Superadmin: end it now (no refund logic — handle money manually).
     */
    public function cancelNow(Subscription $subscription): array
    {
        $this->end($subscription, __('billing::billing.canceled_by_superadmin'));

        return $this->success($subscription, __('billing::billing.canceled_now'));
    }

    /**
     * Superadmin: push the paid-until date (goodwill, offline deal, ...).
     */
    public function extend(Subscription $subscription, CarbonInterface $until): array
    {
        $subscription->forceFill([
            'current_period_end' => $until,
            'trial_ends_at' => $subscription->status === SubscriptionStatus::Trialing ? $until : $subscription->trial_ends_at,
            'status' => $subscription->status === SubscriptionStatus::Trialing ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'grace_ends_at' => null,
            'current_period_start' => $subscription->current_period_start ?? now(),
        ])->save();

        return $this->success($subscription, __('billing::billing.extended', ['date' => $until->format('d M Y')]));
    }

    /**
     * Called once an invoice is fully paid.
     */
    public function invoicePaid(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;

        if (! $subscription) {
            return;
        }

        DB::transaction(function () use ($invoice, $subscription) {
            $package = $invoice->package;

            switch ($invoice->reason) {
                case InvoiceReason::NewSubscription:
                    $subscription->status = SubscriptionStatus::Active;
                    $this->startPeriod($subscription, now());
                    break;

                case InvoiceReason::Change:
                    if ($package) {
                        $subscription->package_id = $package->id;
                        $subscription->snapshot = $package->snapshot();
                        $subscription->scheduled_package_id = null;
                    }
                    $subscription->status = SubscriptionStatus::Active;
                    $this->startPeriod($subscription, now());
                    $this->voidOpen($subscription, InvoiceReason::Renewal);
                    $this->invoices->addCredit($subscription->business, Money::toCents($invoice->proration_excess));
                    break;

                case InvoiceReason::Renewal:
                    if ($package && $package->id !== $subscription->package_id) {
                        // A scheduled downgrade takes effect with the period it was billed for.
                        $subscription->package_id = $package->id;
                        $subscription->snapshot = $package->snapshot();
                    }
                    $subscription->scheduled_package_id = null;

                    // Locked or never-dated: restart today, don't bill for locked time.
                    $start = $subscription->status === SubscriptionStatus::Suspended || ! $invoice->period_start
                        ? now()
                        : $invoice->period_start;

                    $subscription->status = SubscriptionStatus::Active;
                    $this->startPeriod($subscription, $start);
                    break;
            }

            $subscription->grace_ends_at = null;
            $subscription->save();
        });

        $this->notifier->business(
            $subscription->business,
            __('billing::billing.notify_paid_title', ['number' => $invoice->number]),
            __('billing::billing.notify_paid_body', ['date' => $subscription->fresh()->current_period_end?->format('d M Y')]),
            NotificationType::Success,
        );
    }

    /**
     * The daily job (billing:run). Safe to run more than once a day.
     *
     * @return array<string, int> counts per step
     */
    public function runLifecycle(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $counts = ['invoiced' => 0, 'renewed_free' => 0, 'canceled' => 0, 'past_due' => 0, 'suspended' => 0, 'reminded' => 0];
        $reminderDays = max(0, BillingSettings::int('reminder_days'));
        $graceDays = max(0, BillingSettings::int('grace_days'));

        $running = Subscription::query()
            ->with(['business', 'package', 'scheduledPackage', 'coupon'])
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->get();

        foreach ($running as $subscription) {
            if (! $subscription->business || ! $subscription->current_period_end) {
                continue;
            }

            $end = $subscription->current_period_end;
            $next = $subscription->scheduledPackage ?? $subscription->package;

            // 1. Period over.
            if ($end->lte($now)) {
                if ($subscription->cancel_at_period_end) {
                    $this->end($subscription, __('billing::billing.ended_as_requested'));
                    $counts['canceled']++;

                    continue;
                }

                if ($next && $next->isFree()) {
                    $subscription->package_id = $next->id;
                    $subscription->snapshot = $next->snapshot();
                    $subscription->scheduled_package_id = null;
                    $subscription->status = SubscriptionStatus::Active;
                    $this->startPeriod($subscription, $end);
                    $subscription->save();
                    $counts['renewed_free']++;

                    continue;
                }

                $this->ensureRenewalInvoice($subscription, $next) && $counts['invoiced']++;
                $subscription->forceFill([
                    'status' => SubscriptionStatus::PastDue,
                    'grace_ends_at' => $end->copy()->addDays($graceDays),
                ])->save();
                $this->notifier->business(
                    $subscription->business,
                    __('billing::billing.notify_past_due_title'),
                    __('billing::billing.notify_past_due_body', ['date' => $subscription->grace_ends_at->format('d M Y')]),
                    NotificationType::Warning,
                );
                $counts['past_due']++;

                continue;
            }

            // 2. Renewal invoice ahead of the period end.
            if (! $subscription->cancel_at_period_end && $next && ! $next->isFree()
                && $end->copy()->subDays($reminderDays)->lte($now)) {
                if ($this->ensureRenewalInvoice($subscription, $next)) {
                    $counts['invoiced']++;
                    $this->notifier->business(
                        $subscription->business,
                        $subscription->status === SubscriptionStatus::Trialing
                            ? __('billing::billing.notify_trial_ending_title')
                            : __('billing::billing.notify_renewal_title'),
                        __('billing::billing.notify_renewal_body', ['date' => $end->format('d M Y')]),
                        NotificationType::Info,
                    );
                    $counts['reminded']++;
                }
            }
        }

        // 3. Grace over: lock.
        Subscription::query()
            ->with('business')
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->get()
            ->each(function (Subscription $subscription) use (&$counts) {
                $subscription->forceFill(['status' => SubscriptionStatus::Suspended])->save();
                $this->notifier->business(
                    $subscription->business,
                    __('billing::billing.notify_suspended_title'),
                    __('billing::billing.notify_suspended_body'),
                    NotificationType::Warning,
                );
                $counts['suspended']++;
            });

        return $counts;
    }

    // ------------------------------------------------------------------

    /**
     * Push an edited package to businesses already on it. Subscriptions keep a
     * copy of the package; this refreshes it.
     *   'terms': name, modules and limits now; price and period stay until they change plan.
     *   'all':   everything, including the price their next invoice will use.
     *
     * @return int subscriptions updated
     */
    public function applyPackageChanges(Package $package, string $mode): int
    {
        $fresh = $package->snapshot();
        $updated = 0;

        Subscription::query()
            ->where('package_id', $package->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, SubscriptionStatus::current()))
            ->with('business')
            ->get()
            ->each(function (Subscription $subscription) use ($fresh, $mode, &$updated) {
                $old = $subscription->snapshot ?? [];
                $new = $mode === 'all'
                    ? $fresh
                    : array_merge($old, array_intersect_key($fresh, array_flip(['package_id', 'name', 'modules', 'limits'])));

                if ($new == $old) {
                    return;
                }

                $subscription->snapshot = $new;
                $subscription->save();
                $updated++;

                $added = array_values(array_diff($new['modules'] ?? [], $old['modules'] ?? []));
                $removed = array_values(array_diff($old['modules'] ?? [], $new['modules'] ?? []));
                $lines = array_filter([
                    $added ? __('billing::billing.package_change_added', ['modules' => implode(', ', $added)]) : null,
                    $removed ? __('billing::billing.package_change_removed', ['modules' => implode(', ', $removed)]) : null,
                    ($new['limits'] ?? []) != ($old['limits'] ?? []) ? __('billing::billing.package_change_limits') : null,
                    ($new['price'] ?? null) !== ($old['price'] ?? null) ? __('billing::billing.package_change_price', ['price' => $new['price'], 'currency' => BillingSettings::get('currency')]) : null,
                ]);

                if ($subscription->business) {
                    app(BillingNotifier::class)->business(
                        $subscription->business,
                        __('billing::billing.package_change_title', ['name' => $new['name'] ?? '']),
                        implode(' ', $lines) ?: null,
                        url: route('billing.index'),
                    );
                }

                audit(\App\Enums\ActivityLogAction::Updated, __('billing::billing.audit_package_applied', [
                    'name' => $new['name'] ?? '', 'mode' => $mode,
                ]), $subscription, ['old' => $old, 'new' => $new], $subscription->business_id);
            });

        app(\Modules\Billing\Support\BillingEntitlements::class)->forget();

        return $updated;
    }

    public function isUpgrade(Subscription $subscription, Package $package): bool
    {
        $currentDaily = Money::toCents($subscription->price())
            / max(1, $subscription->interval()->days() * $subscription->intervalCount());

        return $package->dailyPrice() > $currentDaily;
    }

    /**
     * Unused share of the current paid period, in cents.
     */
    public function prorationCredit(?Subscription $subscription): int
    {
        if (! $subscription || $subscription->status !== SubscriptionStatus::Active
            || ! $subscription->current_period_start || ! $subscription->current_period_end) {
            return 0;
        }

        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;

        if ($end->lte(now()) || $start->gte($end)) {
            return 0;
        }

        $total = $start->diffInSeconds($end);
        $left = now()->lt($start) ? $total : now()->diffInSeconds($end);

        return (int) round(Money::toCents($subscription->price()) * min(1, $left / max(1, $total)));
    }

    private function startPeriod(Subscription $subscription, CarbonInterface $start): void
    {
        $interval = BillingInterval::from($subscription->snapshot['interval'] ?? 'month');
        $subscription->current_period_start = $start;
        $subscription->current_period_end = $interval->addTo($start, (int) ($subscription->snapshot['interval_count'] ?? 1));
    }

    /**
     * One open renewal invoice per period; returns true if one was issued.
     */
    private function ensureRenewalInvoice(Subscription $subscription, Package $package): bool
    {
        $exists = $subscription->invoices()
            ->where('reason', InvoiceReason::Renewal->value)
            ->where('status', InvoiceStatus::Open->value)
            ->exists();

        if ($exists) {
            return false;
        }

        $start = $subscription->current_period_end;
        $end = $package->interval->addTo($start, $package->interval_count);

        $this->invoices->issue(
            $subscription->business,
            $subscription,
            $package,
            InvoiceReason::Renewal,
            $this->couponForNextInvoice($subscription),
            0,
            $start,
            $end,
        );
        $this->consumeCouponPeriod($subscription);

        return true;
    }

    private function voidOpen(Subscription $subscription, InvoiceReason $reason): void
    {
        $subscription->invoices()
            ->where('reason', $reason->value)
            ->where('status', InvoiceStatus::Open->value)
            ->get()
            ->each(fn (Invoice $invoice) => Money::toCents($invoice->amount_paid) === 0
                ? $this->invoices->void($invoice, __('billing::billing.superseded'))
                : null);
    }

    private function end(Subscription $subscription, string $note): void
    {
        $subscription->invoices()
            ->where('status', InvoiceStatus::Open->value)
            ->get()
            ->each(fn (Invoice $invoice) => Money::toCents($invoice->amount_paid) === 0
                ? $this->invoices->void($invoice, $note)
                : null);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'ended_at' => now(),
            'canceled_at' => $subscription->canceled_at ?? now(),
            'cancel_at_period_end' => false,
            'scheduled_package_id' => null,
        ])->save();
    }

    private function couponPeriods(?Coupon $coupon, bool $consumedOne = false): ?int
    {
        if (! $coupon) {
            return null;
        }

        $periods = match ($coupon->duration) {
            CouponDuration::Once => 1,
            CouponDuration::Repeating => max(1, (int) $coupon->duration_periods),
            CouponDuration::Forever => null,
        };

        return $periods === null ? null : max(0, $periods - ($consumedOne ? 1 : 0));
    }

    private function couponForNextInvoice(Subscription $subscription): ?Coupon
    {
        $coupon = $subscription->coupon_id ? Coupon::withTrashed()->find($subscription->coupon_id) : null;

        if (! $coupon) {
            return null;
        }

        if ($coupon->duration === CouponDuration::Forever) {
            return $coupon;
        }

        return (int) $subscription->coupon_periods_remaining > 0 ? $coupon : null;
    }

    private function consumeCouponPeriod(Subscription $subscription): void
    {
        if ($subscription->coupon_periods_remaining !== null && $subscription->coupon_periods_remaining > 0) {
            $subscription->forceFill(['coupon_periods_remaining' => $subscription->coupon_periods_remaining - 1])->save();
        }
    }
}
