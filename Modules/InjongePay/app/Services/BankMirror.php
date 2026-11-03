<?php

namespace Modules\InjongePay\Services;

use Carbon\CarbonInterface;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\BankTransaction;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;
use Throwable;

/**
 * Reads GTBank's own transaction list for a merchant code and makes our books
 * agree with it:
 *  - our payment, same amount  → confirmed (pending → available)
 *  - no payment of ours        → taken in now (a webhook GTBank never sent)
 *  - different amount or code  → an item; nothing is confirmed
 *  - REVERSED                  → the payment is reversed here too
 */
class BankMirror
{
    public function __construct(
        protected GtbankClient $bank,
        protected PaymentIntake $intake,
        protected PaymentReversal $reversal,
        protected ReconciliationItems $items,
    ) {
    }

    /**
     * @return array{seen: int, matched: int, ingested: int, mismatches: int, reversed: int, foreign: int}|null  null: GTBank gave no usable list
     */
    public function pull(Wallet $wallet, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $result = $this->bank->merchantTransactions($wallet->merchant_code, $from->format('Y-m-d\TH:i:s'), $to->format('Y-m-d\TH:i:s'), $wallet);

        if (! $result->ok() && ! $result->notFound()) {
            return null;
        }

        $summary = ['seen' => 0, 'matched' => 0, 'ingested' => 0, 'mismatches' => 0, 'reversed' => 0, 'foreign' => 0];

        foreach ($result->ok() ? BankPayloads::rows($result->data) : [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            try {
                $summary[$this->apply($wallet, $raw)]++;
                $summary['seen']++;
            } catch (Throwable $e) {
                report($e);
                $summary['mismatches']++;
            }
        }

        return $summary;
    }

    private function apply(Wallet $wallet, array $raw): string
    {
        $tx = BankPayloads::transaction($raw);

        // An answer about another merchant code is never used for this one.
        if (! $tx['transaction_id'] || ($tx['merchant_id'] !== null && $tx['merchant_id'] !== $wallet->merchant_code) || $tx['amount'] === null) {
            return 'foreign';
        }

        $amount = Money::toCents($tx['amount']);
        $row = BankTransaction::query()->updateOrCreate(['transaction_id' => $tx['transaction_id']], [
            'merchant_code' => $wallet->merchant_code, 'wallet_id' => $wallet->id, 'business_id' => $wallet->business_id,
            'amount' => $amount, 'status' => $tx['status'], 'value_date' => $tx['value_date'],
            'bank_created_at' => isset($raw['createdAt']) ? rescue(fn () => \Illuminate\Support\Carbon::parse($raw['createdAt']), null, false) : null,
            'payload' => $raw, 'source' => 'pull', 'last_seen_at' => now(),
        ]);

        $payment = Payment::query()->withoutGlobalScopes()->where('bank_reference', $tx['transaction_id'])->first();

        if ($tx['status'] === 'REVERSED') {
            if ($payment && $payment->payment_status !== Payment::STATUS_REVERSED) {
                $this->reversal->reverse($payment, 'GTBank reported REVERSED');
            }
            $row->forceFill(['match_state' => 'reversed', 'payment_id' => $payment?->id])->save();

            return 'reversed';
        }

        if ($tx['status'] !== 'SUCCESS') {
            $row->forceFill(['match_state' => 'ignored'])->save();

            return 'matched';
        }

        if ($payment) {
            if ($payment->gross_amount !== $amount || $payment->wallet_id !== $wallet->id) {
                $this->items->open('amount_mismatch', $tx['transaction_id'], $wallet, $payment, $amount - $payment->gross_amount, [
                    'bank_amount' => $amount, 'our_amount' => $payment->gross_amount, 'bank_code' => $wallet->merchant_code, 'our_code' => $payment->merchant_code,
                ]);
                $row->forceFill(['match_state' => 'mismatch', 'payment_id' => $payment->id])->save();

                return 'mismatches';
            }

            $this->intake->clear($payment);
            $row->forceFill(['match_state' => 'matched', 'payment_id' => $payment->id])->save();

            return 'matched';
        }

        if (UnallocatedPayment::query()->where('bank_reference', $tx['transaction_id'])->exists()) {
            $row->forceFill(['match_state' => 'held'])->save();

            return 'matched';
        }

        [$outcome, $subject] = $this->intake->receive($raw + ['merchantId' => $wallet->merchant_code], Payment::VIA_BANK_PULL);
        $row->forceFill(['match_state' => $subject instanceof Payment ? 'ingested' : 'held', 'payment_id' => $subject instanceof Payment ? $subject->id : null])->save();

        return 'ingested';
    }
}
