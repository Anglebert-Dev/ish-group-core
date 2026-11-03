<?php

namespace Modules\InjongePay\Services;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * GTBank reversed a payment it had credited (status REVERSED in its list):
 * the money is no longer there, so it comes off the wallet too. If the
 * business has already cashed it out, nothing goes negative: the shortfall is
 * an item for the platform to settle.
 */
class PaymentReversal
{
    public function __construct(protected Ledger $ledger, protected ReconciliationItems $items)
    {
    }

    public function reverse(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::query()->withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === Payment::STATUS_REVERSED) {
                return;
            }

            $wallet = Wallet::query()->withoutGlobalScopes()->findOrFail($locked->wallet_id);
            $bucket = $locked->cleared_at ? LedgerEntry::AVAILABLE : LedgerEntry::PENDING;

            try {
                $this->ledger->post($wallet, LedgerEntry::DEBIT, $bucket, $locked->net_amount, 'payment_reversal', 'reversal:'.$locked->bank_reference, [
                    'reference' => $locked->reference.'R', 'source' => $locked, 'description' => __('injongepay::injongepay.ledger_reversal', ['ref' => $locked->bank_reference]),
                ]);
            } catch (InjongePayException) {
                // Already cashed out: the wallet can't give it back.
                $this->items->open('reversal_shortfall', $locked->bank_reference, $wallet, $locked, $locked->net_amount, ['reason' => $reason, 'available' => $wallet->available_balance]);
            }

            // Fees not credited yet are simply cancelled; credited ones come back off the platform wallets.
            FeeAccrual::query()->where('payment_id', $locked->id)->whereNull('swept_at')->update(['cancelled_at' => now()]);

            foreach (FeeAccrual::query()->where('payment_id', $locked->id)->whereNotNull('swept_at')->whereNull('cancelled_at')->get() as $fee) {
                try {
                    $this->ledger->post(Wallet::query()->withoutGlobalScopes()->findOrFail($fee->wallet_id), LedgerEntry::DEBIT, LedgerEntry::AVAILABLE, $fee->amount, 'payment_reversal',
                        'reversal_fee:'.$locked->bank_reference.':'.$fee->kind, ['source' => $locked, 'description' => __('injongepay::injongepay.ledger_reversal', ['ref' => $locked->bank_reference])]);
                    $fee->forceFill(['cancelled_at' => now()])->save();
                } catch (InjongePayException) {
                    $this->items->open('reversal_shortfall', $locked->bank_reference.':'.$fee->kind, $wallet, $locked, $fee->amount, ['reason' => $reason, 'fee' => $fee->kind]);
                }
            }

            ActivityLogService::muted(fn () => $locked->forceFill(['payment_status' => Payment::STATUS_REVERSED])->save());

            audit('injongepay_payment_reversed', __('injongepay::injongepay.audit_payment_reversed', ['amount' => Money::format($locked->gross_amount), 'ref' => $locked->bank_reference]),
                $locked, ['reason' => $reason, 'was_cleared' => $bucket === LedgerEntry::AVAILABLE], $locked->business_id);
        });
    }
}
