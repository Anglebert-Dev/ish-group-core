<?php

namespace Modules\Distribution\Services;

use App\Enums\NotificationType;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Services\BaseService;
use App\Services\Notification\NotificationService;
use App\Support\Documents\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Distribution\Enums\ChargeStatus;
use Modules\Distribution\Models\Charge;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Support\PlatformAudit;

/**
 * Licences and support for self-hosting customers
 * (docs/self-hosting-build-guide.md §11):
 *  - a licence is charged once, a support renewal each year
 *  - paying a charge with a period extends "support & updates until"
 *  - after that date the site gets no updates; the app keeps working
 *  - reminders go out 30 and 7 days before, and on the day it ends
 */
class LicenceService extends BaseService
{
    /** Days before the end date that a reminder goes out (0 = the day it ends). */
    public const REMINDER_DAYS = [30, 7, 0];

    public const DEFAULT_SUPPORT_MONTHS = 12;

    public function __construct(
        protected DocumentNumber $numbers,
        protected CustomerMailer $mailer,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  array{kind: string, description?: ?string, amount: float|string, support_months?: ?int, due_days?: ?int, note?: ?string}  $data
     */
    public function charge(Site $site, array $data): array
    {

        $open = $site->charges()->where('kind', $data['kind'])->where('status', ChargeStatus::Unpaid->value)->first();
        if ($open) {
            return $this->failure(__('distribution::distribution.charge_open', ['number' => $open->number]));
        }
        $licencePaid = $site->charges()->where('kind', Charge::LICENCE)->where('status', ChargeStatus::Paid->value)->exists();
        if ($data['kind'] === Charge::LICENCE && $licencePaid) {
            return $this->failure(__('distribution::distribution.licence_already_paid'));
        }
        if ($data['kind'] === Charge::SUPPORT && ! $licencePaid) {
            return $this->failure(__('distribution::distribution.renewal_needs_licence'));
        }

        $months = (int) ($data['support_months'] ?? 0);
        $from = $site->support_until && $site->support_until->isFuture() ? $site->support_until->copy()->addDay() : today();

        $charge = new Charge([
            'kind' => $data['kind'],
            'description' => filled($data['description'] ?? null) ? $data['description'] : $this->defaultDescription($site, $data['kind'], $months),
            'modules' => $data['kind'] === Charge::LICENCE ? $site->moduleNames() : null,
            'amount' => $data['amount'],
            'period_from' => $months > 0 ? $from : null,
            'period_until' => $months > 0 ? $from->copy()->addMonthsNoOverflow($months)->subDay() : null,
            'issued_on' => today(),
            'due_on' => today()->addDays((int) ($data['due_days'] ?? 14)),
            'note' => $data['note'] ?? null,
        ]);
        $charge->site_id = $site->id;
        $charge->status = ChargeStatus::Unpaid;
        $charge->number = $this->numbers->next('distribution_charge', 'LIC', $this->platform());
        $charge->save();

        PlatformAudit::log('distribution_charge_created', "Charge {$charge->number} ({$charge->kind}) for site {$site->name}", $charge, ['amount' => $charge->amount]);

        return $this->success($charge, __('distribution::distribution.charge_created', ['number' => $charge->number]));
    }

    /**
     * @param  array{paid_on: string, payment_method?: ?string, payment_reference?: ?string}  $data
     */
    public function markPaid(Charge $charge, array $data): array
    {
        if (! $charge->isOpen()) {
            return $this->failure(__('distribution::distribution.charge_not_open'));
        }

        DB::transaction(function () use ($charge, $data) {
            $charge->forceFill([
                'status' => ChargeStatus::Paid,
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
            ])->save();

            // Its period extends support (never shortens it).
            if ($charge->period_until && (! $charge->site->support_until || $charge->period_until->greaterThan($charge->site->support_until))) {
                $this->moveSupport($charge->site, $charge->period_until);
            }
        });

        PlatformAudit::log('distribution_charge_paid', "Charge {$charge->number} paid", $charge, $data);

        $message = __('distribution::distribution.charge_paid', ['number' => $charge->number]);
        if ($charge->period_until) {
            $message .= ' '.__('distribution::distribution.support_now_until', ['date' => $charge->site->fresh()->support_until?->format('d M Y')]);
        }

        return $this->success($charge, $message);
    }

    /**
     * Correct an unpaid charge (amount, support months, due date, description).
     * Paid or cancelled charges are records and stay as they are.
     *
     * @param  array{amount: float|string, support_months?: ?int, due_on?: ?string, description?: ?string}  $data
     */
    public function update(Charge $charge, array $data): array
    {
        if (! $charge->isOpen()) {
            return $this->failure(__('distribution::distribution.charge_not_open'));
        }

        $site = $charge->site;
        $months = (int) ($data['support_months'] ?? 0);
        $from = $charge->period_from ?? ($site->support_until && $site->support_until->isFuture() ? $site->support_until->copy()->addDay() : today());
        $before = $charge->only(['amount', 'period_until', 'due_on', 'description']);

        $charge->forceFill([
            'amount' => $data['amount'],
            'period_from' => $months > 0 ? $from : null,
            'period_until' => $months > 0 ? $from->copy()->addMonthsNoOverflow($months)->subDay() : null,
            'due_on' => $data['due_on'] ?? $charge->due_on,
            'description' => filled($data['description'] ?? null) ? $data['description'] : $this->defaultDescription($site, $charge->kind, $months),
        ])->save();

        $after = $charge->only(['amount', 'period_until', 'due_on', 'description']);
        $changes = collect($after)->map(fn ($value, $field) => ['from' => self::plain($before[$field]), 'to' => self::plain($value)])
            ->reject(fn ($change) => $change['from'] === $change['to'])->all();
        PlatformAudit::log('distribution_charge_updated', "Charge {$charge->number} corrected", $charge, ['changes' => $changes]);

        return $this->success($charge, __('distribution::distribution.charge_updated', ['number' => $charge->number]));
    }

    private static function plain(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : ($value === null ? null : (string) $value);
    }

    /** Support months a charge carries (from its period). */
    public static function monthsOf(Charge $charge): int
    {
        return $charge->period_from && $charge->period_until ? (int) round($charge->period_from->diffInMonths($charge->period_until->copy()->addDay())) : 0;
    }

    public function cancel(Charge $charge, string $reason): array
    {
        if (! $charge->isOpen()) {
            return $this->failure(__('distribution::distribution.charge_not_open'));
        }

        $charge->forceFill(['status' => ChargeStatus::Cancelled, 'note' => trim(($charge->note ? $charge->note."\n" : '').$reason)])->save();
        PlatformAudit::log('distribution_charge_cancelled', "Charge {$charge->number} cancelled: {$reason}", $charge, ['reason' => $reason]);

        return $this->success($charge, __('distribution::distribution.charge_cancelled', ['number' => $charge->number]));
    }

    /** By hand (a goodwill extension, a correction): needs a reason, audited. */
    public function setSupportUntil(Site $site, ?string $date, string $reason): array
    {
        $old = $site->support_until?->toDateString();
        $this->moveSupport($site, $date ? Carbon::parse($date) : null);

        PlatformAudit::log('distribution_support_changed', "Support for site {$site->name}: ".($old ?? 'no end date').' → '.($date ?? 'no end date').". {$reason}", $site, ['old' => $old, 'new' => $date, 'reason' => $reason]);

        return $this->success($site, $date
            ? __('distribution::distribution.support_now_until', ['date' => Carbon::parse($date)->format('d M Y')])
            : __('distribution::distribution.support_no_end'));
    }

    /**
     * Today's reminders for every site (the daily task). Each stage goes once
     * per end date; a new end date starts over.
     *
     * @return int reminders sent
     */
    public function sendDueReminders(): int
    {
        $sent = 0;
        Site::query()->whereNotNull('support_until')->with('charges')->each(function (Site $site) use (&$sent) {
            $stage = $this->dueStage($site);
            if ($stage !== null && $this->remind($site, $stage)['status']) {
                $sent++;
            }
        });

        return $sent;
    }

    /**
     * Send a reminder now: the given stage, or the one that fits today.
     */
    public function remind(Site $site, ?int $stage = null): array
    {
        if (! $site->support_until) {
            return $this->failure(__('distribution::distribution.support_no_end'));
        }

        $stage ??= max(0, min(30, (int) $site->supportDaysLeft()));
        $openRenewal = $site->charges->first(fn (Charge $charge) => $charge->isOpen() && $charge->period_until);

        if ($site->contact_email) {
            $this->mailer->sendSupportReminder($site, $site->contact_email, $openRenewal);
        }

        $title = $site->hasSupport()
            ? __('distribution::distribution.reminder_admin_title', ['site' => $site->name, 'date' => $site->support_until->format('d M Y')])
            : __('distribution::distribution.reminder_admin_ended', ['site' => $site->name, 'date' => $site->support_until->format('d M Y')]);
        $this->notifications->notifySuperadmins($title, $site->contact_email ? null : __('distribution::distribution.reminder_no_email'), NotificationType::Warning, route('superadmin.distribution.sites.show', $site, false));

        $site->forceFill(['support_reminder_stage' => (string) $stage, 'support_reminded_for' => $site->support_until])->saveQuietly();
        PlatformAudit::log('distribution_support_reminder', "Support reminder ({$stage} days) for site {$site->name}", $site, ['stage' => $stage, 'email' => $site->contact_email]);

        return $this->success($site, $site->contact_email
            ? __('distribution::distribution.reminder_sent', ['email' => $site->contact_email])
            : __('distribution::distribution.reminder_no_email'));
    }

    /**
     * The reminder due today and not sent yet, if any: the most urgent stage
     * reached (30, 7 or 0 days), unless it or a more urgent one already went
     * for this end date.
     */
    public function dueStage(Site $site): ?int
    {
        $left = $site->supportDaysLeft();
        if ($left === null) {
            return null;
        }

        $reached = array_filter(self::REMINDER_DAYS, fn (int $days) => $left <= $days);
        if ($reached === []) {
            return null;
        }

        $stage = min($reached);
        $sent = $site->support_reminded_for?->equalTo($site->support_until) && $site->support_reminder_stage !== null
            ? (int) $site->support_reminder_stage
            : null;

        return $sent !== null && $sent <= $stage ? null : $stage;
    }

    private function moveSupport(Site $site, ?CarbonInterface $until): void
    {
        // A new end date: its reminders start over.
        $site->forceFill(['support_until' => $until, 'support_reminder_stage' => null, 'support_reminded_for' => null])->save();
    }

    private function defaultDescription(Site $site, string $kind, int $months): string
    {
        if ($kind === Charge::LICENCE && $months === 0) {
            return __('distribution::distribution.charge_licence_text_only', ['modules' => implode(', ', $site->moduleNames()) ?: '—']);
        }

        return $kind === Charge::LICENCE
            ? __('distribution::distribution.charge_licence_text', ['modules' => implode(', ', $site->moduleNames()) ?: '—', 'months' => $months])
            : __('distribution::distribution.charge_support_text', ['months' => $months, 'site' => $site->name]);
    }

    private function platform(): ?Business
    {
        $id = (int) System::getProperty('primary_business_id');

        return $id ? Business::query()->find($id) : Business::query()->first();
    }
}
