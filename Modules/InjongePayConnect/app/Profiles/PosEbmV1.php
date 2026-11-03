<?php

namespace Modules\InjongePayConnect\Profiles;

use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Support\Money;
use Modules\InjongePayConnect\Models\Confirmation;

/**
 * The Injonge EBM/POS contract (/api/partner/v1), answer for answer as the
 * production app gave it. Only this class shapes what the partner receives.
 */
final class PosEbmV1
{
    public const NAME = 'pos-ebm-v1';

    public static function payment(Payment $payment, ?Confirmation $confirmation): array
    {
        $status = match (true) {
            $payment->payment_status === Payment::STATUS_REVERSED => 'REVERSED',
            $confirmation?->status === Confirmation::CONFIRMED => 'CONFIRMED',
            default => 'AVAILABLE',
        };

        return [
            'id' => $payment->uuid,
            'amount' => Money::toFloat($payment->gross_amount),
            'fee_amount' => Money::toFloat($payment->fee_amount),
            'currency' => 'RWF',
            'network' => $payment->channel,
            'payer_name' => $payment->buyer_name,
            'payer_phone' => $payment->buyer_phone,
            'provider_reference' => $payment->bank_reference,
            'received_at' => self::time($payment->created_at),
            'status' => $status,
            'invoice_no' => $confirmation->invoice_no ?? null,
            'is_partial' => $confirmation->is_partial ?? null,
            'note' => $confirmation->note ?? null,
            'invoice_total' => isset($confirmation->invoice_total) ? (float) $confirmation->invoice_total : null,
            'balance_after' => isset($confirmation->balance_after) ? (float) $confirmation->balance_after : null,
            'confirmed_by_name' => $confirmation->confirmed_by_name ?? null,
            'confirmed_at' => $confirmation ? self::time($confirmation->confirmed_at) : null,
        ];
    }

    public static function confirmation(Confirmation $confirmation): array
    {
        $payment = $confirmation->payment;

        return [
            'payment_id' => $payment?->uuid,
            'amount' => $payment ? Money::toFloat($payment->gross_amount) : 0.0,
            'invoice_no' => $confirmation->invoice_no,
            'is_partial' => (bool) $confirmation->is_partial,
            'note' => $confirmation->note,
            'invoice_total' => isset($confirmation->invoice_total) ? (float) $confirmation->invoice_total : null,
            'balance_after' => isset($confirmation->balance_after) ? (float) $confirmation->balance_after : null,
            'status' => $confirmation->status,
            'confirmed_by_name' => $confirmation->confirmed_by_name,
            'confirmed_at' => self::time($confirmation->confirmed_at),
        ];
    }

    /** ISO 8601 in Kigali time (+02:00), as partners have always received it. */
    public static function time(mixed $value): ?string
    {
        return $value ? $value->copy()->setTimezone((string) config('injongepayconnect.timezone', 'Africa/Kigali'))->toIso8601String() : null;
    }
}
