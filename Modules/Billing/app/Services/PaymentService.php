<?php

namespace Modules\Billing\Services;

use App\Enums\NotificationType;
use App\Libraries\UploadLibrary;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Support\Money;

/**
 * Offline payments: a business submits one (with proof) and a superadmin
 * approves it, or a superadmin records one directly (approved at once).
 * An invoice is paid when approved payments cover its total.
 */
class PaymentService extends BaseService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected BillingNotifier $notifier,
    ) {
    }

    /**
     * @param  array{amount: mixed, method: string, reference?: ?string, paid_on: string, notes?: ?string}  $data
     */
    public function submit(Invoice $invoice, User $by, array $data, ?UploadedFile $proof = null, bool $approveNow = false): array
    {
        if (! $invoice->isOpen()) {
            return $this->failure(__('billing::billing.invoice_not_open'));
        }

        $amount = Money::toCents($data['amount']);
        if ($amount <= 0) {
            return $this->failure(__('billing::billing.amount_invalid'));
        }

        $payment = DB::transaction(function () use ($invoice, $by, $data, $proof, $amount) {
            return Payment::query()->create([
                'business_id' => $invoice->business_id,
                'invoice_id' => $invoice->id,
                'amount' => Money::fromCents($amount),
                'method' => PaymentMethod::from($data['method']),
                'reference' => $data['reference'] ?? null,
                'paid_on' => $data['paid_on'],
                // Private disk: proofs may contain bank details.
                'proof_path' => $proof ? UploadLibrary::store($proof, 'billing/proofs', 'local') : null,
                'status' => PaymentStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'submitted_by' => $by->id,
            ]);
        });

        if ($approveNow) {
            return $this->approve($payment, $by);
        }

        $this->notifier->superadmins(
            __('billing::billing.notify_payment_submitted_title', ['business' => $invoice->business->name]),
            __('billing::billing.notify_payment_submitted_body', [
                'amount' => Money::format($payment->amount, $invoice->currency),
                'number' => $invoice->number,
            ]),
            \Illuminate\Support\Facades\Route::has('superadmin.billing.payments.index')
                ? route('superadmin.billing.payments.index', ['status' => 'pending'], false)
                : null,
        );

        return $this->success($payment, __('billing::billing.payment_submitted'));
    }

    public function approve(Payment $payment, User $by): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $this->failure(__('billing::billing.payment_not_pending'));
        }

        $invoice = null;

        DB::transaction(function () use ($payment, $by, &$invoice) {
            $payment->forceFill([
                'status' => PaymentStatus::Approved,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
            ])->save();

            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);
            $invoice->amount_paid = Money::fromCents(Money::toCents($invoice->amount_paid) + Money::toCents($payment->amount));

            if ($invoice->status === InvoiceStatus::Open && Money::toCents($invoice->amount_paid) >= Money::toCents($invoice->total)) {
                $invoice->status = InvoiceStatus::Paid;
                $invoice->paid_at = now();
            }

            $invoice->save();
        });

        app(\App\Services\ActivityLog\ActivityLogService::class)->log(
            \App\Enums\ActivityLogAction::Approved,
            __('body.audit_log_payment_approved', ['amount' => Money::format($payment->amount, $invoice->currency), 'number' => $invoice->number]),
            $payment,
            ['amount' => $payment->amount, 'invoice' => $invoice->number],
            $payment->business_id,
        );

        if ($invoice->status === InvoiceStatus::Paid) {
            $this->subscriptions->invoicePaid($invoice);
        } else {
            $this->notifier->business(
                $invoice->business,
                __('billing::billing.notify_partial_title', ['number' => $invoice->number]),
                __('billing::billing.notify_partial_body', ['balance' => Money::format(Money::fromCents($invoice->balanceCents()), $invoice->currency)]),
                NotificationType::Info,
            );
        }

        return $this->success($payment, __('billing::billing.payment_approved'));
    }

    public function reject(Payment $payment, User $by, string $reason): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $this->failure(__('billing::billing.payment_not_pending'));
        }

        $payment->forceFill([
            'status' => PaymentStatus::Rejected,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        app(\App\Services\ActivityLog\ActivityLogService::class)->log(
            \App\Enums\ActivityLogAction::Rejected,
            __('body.audit_log_payment_rejected', ['amount' => Money::format($payment->amount, $payment->invoice?->currency), 'number' => $payment->invoice?->number]),
            $payment,
            ['reason' => $reason],
            $payment->business_id,
        );

        $this->notifier->business(
            $payment->business,
            __('billing::billing.notify_rejected_title', ['number' => $payment->invoice?->number]),
            $reason,
            NotificationType::Warning,
        );

        return $this->success($payment, __('billing::billing.payment_rejected'));
    }
}
