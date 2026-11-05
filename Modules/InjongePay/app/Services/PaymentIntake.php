<?php

namespace Modules\InjongePay\Services;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Counter;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformAlert;

/**
 * Money GTBank says it credited to a merchant code. One GTBank transactionId
 * becomes exactly one payment, however many times it arrives:
 *
 *  - The wallet gets the net in its *pending* balance. It becomes available
 *    (can be cashed out) only once GTBank's own transaction list confirms it
 *    (clear()); a payment read from that list is cleared at once.
 *  - The fee shares are accrued and credited to the platform's wallets in
 *    batches (FeeSweep), so payments never queue behind the same two rows.
 *  - A code that can't take money (unknown, switched off, not active) never
 *    loses it: the payment is parked for the platform to allocate.
 */
class PaymentIntake
{
    public const CREATED = 'created';

    public const DUPLICATE = 'duplicate';

    public const PARKED = 'parked';

    public function __construct(protected Ledger $ledger, protected Fees $fees, protected InternalAccounts $internal)
    {
    }

    /**
     * @param  array  $transaction  GTBank's transaction (webhook body or a row of its transaction list)
     * @return array{0: string, 1: Payment|UnallocatedPayment}
     */
    public function receive(array $transaction, string $via): array
    {
        $tx = BankPayloads::transaction($transaction);

        if (! $tx['transaction_id'] || ! $tx['merchant_id'] || $tx['amount'] === null) {
            throw new InjongePayException(__('injongepay::injongepay.err_transaction_incomplete'));
        }

        try {
            $gross = Money::toCents($tx['amount']);
        } catch (\InvalidArgumentException) {
            throw new InjongePayException(__('injongepay::injongepay.err_amount_format'));
        }
        if ($gross <= 0) {
            throw new InjongePayException(__('injongepay::injongepay.err_amount_positive'));
        }

        if ($existing = $this->existing($tx['transaction_id'])) {
            if ($existing instanceof Payment && $via === Payment::VIA_BANK_PULL) {
                $this->clear($existing);
            }

            return [self::DUPLICATE, $existing];
        }

        $wallet = Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('merchant_code', $tx['merchant_id'])->first();

        if ($reason = $this->cannotTake($wallet) ?? $this->fromBeforeTakeover($wallet, $tx)) {
            return $this->park($tx, $gross, $wallet, $reason, $transaction);
        }

        // Taken before the transaction so payments never wait on the counter row.
        $reference = Counter::reference('PAY');

        try {
            $payment = DB::transaction(fn () => $this->create($wallet, $tx, $gross, $reference, $via));
        } catch (QueryException $e) {
            // Only a second delivery of the same transactionId lands here; anything else is a real error.
            if ($existing = $this->existing($tx['transaction_id'])) {
                return [self::DUPLICATE, $existing];
            }

            throw $e;
        }

        audit('injongepay_payment_received', __('injongepay::injongepay.audit_payment_received', [
            'amount' => Money::format($gross), 'code' => $wallet->merchant_code, 'ref' => $tx['transaction_id'],
        ]), $payment, ['via' => $via, 'gross' => $gross, 'net' => $payment->net_amount, 'fee' => $payment->fee_amount, 'cleared' => $payment->isCleared()], $wallet->business_id);

        return [self::CREATED, $payment];
    }

    /**
     * Credits parked money to a wallet the platform chose (e.g. once the code is
     * active, or the business the payer meant). The reason is kept with it.
     */
    public function allocate(UnallocatedPayment $row, Wallet $wallet, string $note): Payment
    {
        if ($row->reason === 'before_takeover') {
            throw new InjongePayException(__('injongepay::injongepay.err_allocate_before_takeover'));
        }

        if (! $wallet->is_enabled || $wallet->isInternal()) {
            throw new InjongePayException(__('injongepay::injongepay.err_allocate_wallet'));
        }

        $reference = Counter::reference('PAY');

        $payment = DB::transaction(function () use ($row, $wallet, $note, $reference) {
            $locked = UnallocatedPayment::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== UnallocatedPayment::OPEN) {
                throw new InjongePayException(__('injongepay::injongepay.err_already_resolved'));
            }

            $tx = BankPayloads::transaction($locked->payload);
            $payment = $this->create($wallet, $tx, $locked->amount, $reference, Payment::VIA_ALLOCATED);

            $locked->forceFill([
                'status' => UnallocatedPayment::ALLOCATED, 'payment_id' => $payment->id, 'wallet_id' => $wallet->id, 'business_id' => $wallet->business_id,
                'resolved_by' => auth()->id(), 'resolved_by_name' => auth()->user()?->name, 'resolved_at' => now(), 'resolution_note' => $note,
            ])->save();

            return $payment;
        });

        audit('injongepay_payment_allocated', __('injongepay::injongepay.audit_payment_allocated', [
            'amount' => Money::format($row->amount), 'ref' => $row->bank_reference, 'code' => $wallet->merchant_code,
        ]), $payment, ['note' => $note, 'unallocated' => $row->uuid], $wallet->business_id);

        return $payment;
    }

    /**
     * GTBank's own list confirmed this payment: its money moves from pending to available.
     */
    public function clear(Payment $payment): void
    {
        if ($payment->isCleared() || $payment->payment_status === Payment::STATUS_REVERSED) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $locked = Payment::query()->withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->cleared_at !== null || $locked->payment_status === Payment::STATUS_REVERSED) {
                return;
            }

            $wallet = Wallet::query()->withoutGlobalScopes()->findOrFail($locked->wallet_id);
            $this->ledger->clear($wallet, $locked->net_amount, 'payment_cleared', 'clear:'.$locked->bank_reference, [
                'reference' => $locked->reference.'C', 'source' => $locked, 'description' => __('injongepay::injongepay.ledger_cleared', ['ref' => $locked->bank_reference]),
            ]);

            ActivityLogService::muted(fn () => $locked->forceFill(['cleared_at' => now()])->save());
            $payment->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function create(Wallet $wallet, array $tx, int $gross, string $reference, string $via): Payment
    {
        $split = $this->fees->split($wallet, $gross);

        $payment = ActivityLogService::muted(fn () => Payment::query()->create([
            'business_id' => $wallet->business_id,
            'wallet_id' => $wallet->id,
            'merchant_code' => $wallet->merchant_code,
            'reference' => $reference,
            'bank_reference' => $tx['transaction_id'],
            'channel' => $tx['channel'],
            'buyer_name' => $tx['payer_name'],
            'buyer_phone' => $tx['payer_account'],
            'value_date' => $tx['value_date'],
            'initiating_party_code' => $tx['channel_id'],
            'gross_amount' => $gross,
            'fee_rate' => $split['rate'],
            'fee_amount' => $split['fee'],
            'bank_cut_amount' => $split['bank_cut'],
            'platform_revenue_amount' => $split['platform'],
            'net_amount' => $split['net'],
            'payment_status' => Payment::STATUS_AVAILABLE,
            'received_via' => $via,
            'insert_by' => auth()->id(),
        ]));

        $entry = $this->ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::PENDING, $split['net'], 'payment', 'payment:'.$tx['transaction_id'], [
            'reference' => $reference,
            'source' => $payment,
            'description' => __('injongepay::injongepay.ledger_payment', ['ref' => $tx['transaction_id']]),
            'counterparty_name' => $tx['payer_name'],
            'counterparty_phone' => $tx['payer_account'],
        ]);

        foreach ([[Wallet::ROLE_BANK_CUT, $split['bank_cut']], [Wallet::ROLE_PLATFORM_REVENUE, $split['platform']]] as [$kind, $amount]) {
            if ($amount > 0) {
                FeeAccrual::query()->create(['payment_id' => $payment->id, 'wallet_id' => $this->internal->wallet($kind)->id, 'kind' => $kind, 'amount' => $amount]);
            }
        }

        ActivityLogService::muted(fn () => $payment->forceFill(['ledger_entry_id' => $entry->id])->save());

        // From GTBank's own list (or no bank at all, in test mode): nothing left to confirm.
        if ($via === Payment::VIA_BANK_PULL || ! GtbankClient::isConfigured()) {
            $this->clear($payment);
        }

        return $payment;
    }

    /** @return array{0: string, 1: UnallocatedPayment} */
    private function park(array $tx, int $gross, ?Wallet $wallet, string $reason, array $raw): array
    {
        $row = UnallocatedPayment::query()->firstOrCreate(['bank_reference' => $tx['transaction_id']], [
            'merchant_code' => $tx['merchant_id'],
            'wallet_id' => $wallet?->id,
            'business_id' => $wallet?->business_id,
            'amount' => $gross,
            'payload' => $raw,
            'reason' => $reason,
            'status' => UnallocatedPayment::OPEN,
        ]);

        if ($row->wasRecentlyCreated) {
            audit('injongepay_payment_parked', __('injongepay::injongepay.audit_payment_parked', [
                'amount' => Money::format($gross), 'code' => $tx['merchant_id'], 'reason' => __('injongepay::injongepay.park_reason_'.$reason),
            ]), $row, ['reason' => $reason, 'bank_reference' => $tx['transaction_id']], $wallet?->business_id);

            PlatformAlert::send('list injongepay_platform.reconciliation', __('injongepay::injongepay.notify_parked_title'),
                __('injongepay::injongepay.audit_payment_parked', ['amount' => Money::format($gross), 'code' => $tx['merchant_id'], 'reason' => __('injongepay::injongepay.park_reason_'.$reason)]),
                route('injongepay.platform.unallocated.index', [], false));
        }

        return [$row->wasRecentlyCreated ? self::PARKED : self::DUPLICATE, $row];
    }

    private function cannotTake(?Wallet $wallet): ?string
    {
        return match (true) {
            $wallet === null => 'unknown_code',
            $wallet->isInternal() => 'internal',
            ! $wallet->is_enabled => 'wallet_disabled',
            ! $wallet->acceptsPayments(GtbankClient::isConfigured()) => 'not_active',
            default => null,
        };
    }

    /**
     * A webhook for a day before we took this code over (GTBank resending an old
     * one): that money was handled elsewhere. A webhook has no time, so one from
     * the takeover day itself waits as pending and GTBank's list settles it.
     */
    private function fromBeforeTakeover(Wallet $wallet, array $tx): ?string
    {
        if ($wallet->live_from === null || $tx['value_date'] === null) {
            return null;
        }

        $day = rescue(fn () => Carbon::parse($tx['value_date']), null, false);

        return $day && $day->startOfDay()->lt($wallet->live_from->copy()->startOfDay()) ? 'before_takeover' : null;
    }

    private function existing(string $bankReference): Payment|UnallocatedPayment|null
    {
        return Payment::query()->withoutGlobalScopes()->where('bank_reference', $bankReference)->first()
            ?? UnallocatedPayment::query()->where('bank_reference', $bankReference)->first();
    }
}
