<?php

namespace Modules\InjongePay\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankAnswers;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Jobs\SubmitCashout;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\CashoutEvent;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Counter;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformAlert;

/**
 * Money leaving the platform to a bank account or wallet through GTBank.
 *
 *   QUEUED      wallet debited, waiting for the job to send it
 *   SUBMITTING  being sent (if the job dies here, it is treated as UNKNOWN)
 *   SUBMITTED   GTBank accepted it
 *   UNKNOWN     GTBank's answer was unclear: it may have gone
 *   COMPLETED   two agreeing signals said ACSC (two status reads, or a status read and the webhook)
 *   FAILED      two agreeing signals said RJCT/CANC, or GTBank refused it and never heard of it;
 *               the money is back in the wallet
 *   NEEDS_REVIEW  no final answer in time: the platform decides with GTBank, never the business
 *
 * Nothing is ever sent twice automatically: a reference GTBank may have is
 * only ever asked about.
 */
class CashoutService
{
    // Minutes between status checks: soon at first, then less often.
    public const BACKOFF = [1, 2, 5, 10, 20, 30, 60];

    // "Not paid" must be said this far apart before money comes back by itself.
    public const REJECTED_APART_MINUTES = 10;

    // After money came back, how long and how often we keep asking GTBank.
    public const WATCH_CLOSED_DAYS = 7;

    public const WATCH_EVERY_MINUTES = 60;

    // An unclear submit: how long before a person has to decide.
    public const UNKNOWN_GRACE_MINUTES = 120;

    /** What check() returns when GTBank gave no answer this time. */
    public const NO_ANSWER = 'no_answer';

    public function __construct(protected Ledger $ledger, protected GtbankClient $bank, protected Approvals $approvals, protected ReconciliationItems $items)
    {
    }

    /** Checks before asking: usable account, whole RWF, enough available money. */
    public function validate(Wallet $wallet, SettlementAccount $account, int $amount): void
    {
        if (! $wallet->is_enabled) {
            throw new InjongePayException(__('injongepay::injongepay.err_wallet_off'));
        }

        if ($account->business_id !== $wallet->business_id || ! $account->isUsable()) {
            throw new InjongePayException(__('injongepay::injongepay.err_account_not_usable'));
        }

        if ($amount <= 0 || ! Money::isWholeUnits($amount)) {
            throw new InjongePayException(__('injongepay::injongepay.err_whole_rwf'));
        }

        if ($amount > $wallet->available_balance) {
            throw new InjongePayException(__('injongepay::injongepay.err_insufficient_balance'));
        }
    }

    /** Runs an approved cash-out request: debit now, send from the queue. */
    public function start(Approval $approval): ?Cashout
    {
        $reference = Counter::reference('CSH');

        if (! $claimed = $this->approvals->claim($approval)) {
            return Cashout::query()->withoutGlobalScopes()->where('approval_id', $approval->id)->first();
        }

        try {
            $cashout = DB::transaction(function () use ($claimed, $reference) {
                $wallet = Wallet::query()->withoutGlobalScopes()->findOrFail($claimed->wallet_id);
                $account = SettlementAccount::query()->withoutGlobalScopes()->findOrFail($claimed->payload['settlement_account_id']);
                // Again, now: the account may have been switched off while waiting for approval.
                $this->validate($wallet, $account, $claimed->amount);

                $cashout = Cashout::query()->create([
                    'reference' => $reference, 'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id,
                    'settlement_account_id' => $account->id, 'approval_id' => $claimed->id, 'amount' => $claimed->amount,
                    'account' => $account->account, 'account_holder' => $account->verified_names ?: $account->holder_name, 'bank_name' => $account->bank_name,
                    'status' => Cashout::QUEUED, 'insert_by' => $claimed->requested_by,
                ]);

                $entry = $this->ledger->post($wallet, LedgerEntry::DEBIT, LedgerEntry::AVAILABLE, $claimed->amount, 'cashout', 'cashout:'.$cashout->id, [
                    'reference' => $reference, 'source' => $cashout, 'description' => __('injongepay::injongepay.ledger_cashout', ['account' => $account->label()]),
                ]);

                $cashout->forceFill(['ledger_entry_id' => $entry->id])->save();
                $claimed->forceFill(['subject_type' => $cashout->getMorphClass(), 'subject_id' => $cashout->id])->save();
                $this->event($cashout, null, Cashout::QUEUED, 'request', null, ['approval' => $claimed->uuid]);

                return $cashout;
            });
        } catch (InjongePayException $e) {
            $this->approvals->fail($claimed, $e->getMessage());

            throw $e;
        }

        audit('injongepay_cashout_queued', __('injongepay::injongepay.audit_cashout_queued', ['amount' => Money::format($cashout->amount), 'ref' => $cashout->reference, 'account' => $cashout->account]),
            $cashout, ['approval' => $claimed->uuid], $cashout->business_id);

        SubmitCashout::dispatch($cashout->id)->afterCommit();

        return $cashout;
    }

    /** Sends it to GTBank once. Whatever happens, the status checks take over. */
    public function submit(Cashout $cashout): void
    {
        $taken = DB::transaction(function () use ($cashout) {
            $locked = Cashout::query()->withoutGlobalScopes()->whereKey($cashout->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Cashout::QUEUED) {
                return null;
            }

            $this->move($locked, Cashout::SUBMITTING, 'submit');

            return $locked;
        });

        if (! $taken) {
            return;
        }

        if (! GtbankClient::isConfigured()) {
            // Test mode: no bank, nothing leaves; it completes at once.
            $this->complete($taken, 'submit', __('injongepay::injongepay.test_mode'));

            return;
        }

        $wallet = Wallet::query()->withoutGlobalScopes()->find($taken->wallet_id);
        $result = $this->bank->disbursementTransfer([
            'reference' => $taken->reference,
            'beneficiaryAccount' => $taken->account,
            'amount' => intdiv($taken->amount, 100),
            'currency' => 'RWF',
            'remarks' => mb_substr('Cash-out '.$taken->reference, 0, 50),
            'initiatorName' => mb_substr(($wallet?->name ?? 'Injonge Pay').'-'.($wallet?->merchant_code ?? ''), 0, 50),
        ], $taken);

        DB::transaction(function () use ($taken, $result) {
            $locked = Cashout::query()->withoutGlobalScopes()->whereKey($taken->id)->lockForUpdate()->first();

            if ($locked->status !== Cashout::SUBMITTING) {
                return;
            }

            [$status, $outcome] = match (true) {
                $result->ok() => [Cashout::SUBMITTED, 'accepted'],
                $result->duplicate() => [Cashout::UNKNOWN, 'duplicate'],
                $result->rejected() => [Cashout::UNKNOWN, 'refused'],
                default => [Cashout::UNKNOWN, 'unknown'],
            };

            $locked->forceFill([
                'submit_outcome' => $outcome, 'submitted_at' => now(),
                'bank_reference' => BankPayloads::disbursement($result->data)['reference'] ?? $locked->bank_reference,
                'bank_status' => BankPayloads::disbursement($result->data)['status'],
                'failure_reason' => $result->ok() ? null : $result->message(),
                'next_check_at' => now()->addMinutes(self::BACKOFF[0]),
            ])->save();

            $this->move($locked, $status, 'submit', $result->ok() ? null : $result->message(), ['outcome' => $outcome, 'http' => $result->httpStatus, 'code' => $result->code], $result->callId);
        });
    }

    /**
     * After money came back, keep asking for a while: a late "paid" must be found
     * even if GTBank never sends the webhook. This only reads.
     */
    public function watchClosed(Cashout $cashout): string
    {
        if ($cashout->status !== Cashout::FAILED || ! GtbankClient::isConfigured()) {
            return 'skipped';
        }

        $result = $this->bank->disbursementStatus($cashout->reference, $cashout);
        $answer = BankAnswers::disbursement($result, $cashout->reference, $cashout->amount, $cashout->account);

        $cashout->forceFill(['last_checked_at' => now(), 'last_answer' => $answer['state'], 'next_check_at' => now()->addMinutes(self::WATCH_EVERY_MINUTES)])->save();

        if ($answer['state'] === BankAnswers::COMPLETED) {
            $this->paidAfterFailed($cashout);
        }

        return $result->unknown() ? self::NO_ANSWER : $answer['state'];
    }

    /** GTBank paid something we already gave back: only a person can fix that, and now. */
    private function paidAfterFailed(Cashout $cashout): void
    {
        $cashout->forceFill(['next_check_at' => null])->save();
        $this->items->open('cashout_paid_after_failed', $cashout->reference, Wallet::query()->withoutGlobalScopes()->find($cashout->wallet_id), $cashout, $cashout->amount);
    }

    /** Ask GTBank where it stands and act only on a clear, matching answer. */
    public function check(Cashout $cashout, string $source = 'poller'): string
    {
        if ($cashout->isFinal() || $cashout->status === Cashout::QUEUED) {
            return 'skipped';
        }

        if (! GtbankClient::isConfigured()) {
            return 'skipped';
        }

        $result = $this->bank->disbursementStatus($cashout->reference, $cashout);
        $answer = BankAnswers::disbursement($result, $cashout->reference, $cashout->amount, $cashout->account);

        DB::transaction(function () use ($cashout, $answer, $result, $source) {
            $locked = Cashout::query()->withoutGlobalScopes()->whereKey($cashout->id)->lockForUpdate()->first();

            if ($locked->isFinal()) {
                return;
            }

            $state = $answer['state'];
            $d = $answer['details'];
            $previous = $locked->last_answer;
            $previousAt = $locked->last_checked_at;

            $locked->forceFill([
                'checks' => $locked->checks + 1, 'last_checked_at' => now(), 'last_answer' => $state,
                'bank_status' => $d['status'] ?? $locked->bank_status,
                'cbs_reference' => $d['cbs_reference'] ?? $locked->cbs_reference,
                'receiver_names' => $d['receiver'] ?? $locked->receiver_names,
                'not_found_count' => $state === BankAnswers::NOT_FOUND ? $locked->not_found_count + 1 : $locked->not_found_count,
                'next_check_at' => now()->addMinutes(self::BACKOFF[min($locked->checks, count(self::BACKOFF) - 1)]),
            ])->save();

            // A SUBMITTING the job never finished is unclear: it may have reached GTBank.
            if ($locked->status === Cashout::SUBMITTING && $locked->updated_at->lt(now()->subMinutes(2))) {
                $this->move($locked, Cashout::UNKNOWN, $source, __('injongepay::injongepay.cashout_submit_interrupted'));
            }

            match ($state) {
                BankAnswers::COMPLETED => $this->agree($locked, 'completed', $previous === BankAnswers::COMPLETED || $locked->webhook_status === 'ACSC', $source, $result->callId),
                BankAnswers::FAILED => $this->agree($locked, 'failed', $this->rejectedTwice($previous, $previousAt) || in_array($locked->webhook_status, ['RJCT', 'CANC'], true), $source, $result->callId),
                BankAnswers::NOT_FOUND => $this->notFound($locked, $source, $result->callId),
                BankAnswers::INCONCLUSIVE => $this->inconclusive($locked, $answer, $source, $result->callId),
                default => $this->waitOrReview($locked, $source),
            };
        });

        return $result->unknown() ? self::NO_ANSWER : $answer['state'];
    }

    /** GTBank's disbursement webhook: recorded as one signal, then a status read decides. */
    public function webhook(Cashout $cashout, array $body): void
    {
        $d = BankPayloads::disbursement($body);
        $answer = BankAnswers::disbursement(new BankResult(BankResult::OK, $body), $cashout->reference, $cashout->amount, $cashout->account);

        if ($answer['state'] === BankAnswers::INCONCLUSIVE && $d['status'] !== null && ! in_array(strtoupper($d['status']), ['PENDING'], true)) {
            $this->items->open('cashout_mismatch', $cashout->reference.':webhook', Wallet::query()->withoutGlobalScopes()->find($cashout->wallet_id), $cashout, $cashout->amount, ['webhook' => $body, 'reason' => $answer['reason']]);

            return;
        }

        $cashout->forceFill(['webhook_status' => $d['status'] ? strtoupper($d['status']) : null, 'webhook_at' => now()])->save();

        if ($cashout->status === Cashout::FAILED && $answer['state'] === BankAnswers::COMPLETED) {
            $this->paidAfterFailed($cashout);
        }

        $this->event($cashout, $cashout->status, $cashout->status, 'webhook', null, ['status' => $d['status']]);

        if (! $cashout->isFinal()) {
            $this->check($cashout->fresh(), 'webhook');
        }
    }

    /** The platform, after checking with GTBank. */
    public function resolve(Cashout $cashout, string $outcome, string $note): void
    {
        DB::transaction(function () use ($cashout, $outcome, $note) {
            $locked = Cashout::query()->withoutGlobalScopes()->whereKey($cashout->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                throw new InjongePayException(__('injongepay::injongepay.err_already_resolved'));
            }

            $outcome === 'completed' ? $this->complete($locked, 'operator', $note) : $this->fail($locked, 'operator', $note);
        });
    }

    private function agree(Cashout $cashout, string $state, bool $confirmed, string $source, ?int $callId): void
    {
        if (! $confirmed) {
            // One signal so far; the next read must say the same. "Not paid" must hold over time.
            $cashout->forceFill(['next_check_at' => now()->addMinutes($state === 'failed' ? self::REJECTED_APART_MINUTES + 1 : 1)])->save();
            $this->event($cashout, $cashout->status, $cashout->status, $source, __('injongepay::injongepay.cashout_first_signal_'.$state), [], $callId);

            return;
        }

        if ($state === 'completed') {
            $this->complete($cashout, $source, null, $callId);

            return;
        }

        // We never had a clear yes when sending, so this "no" isn't enough to give money back alone.
        if ($cashout->submit_outcome !== 'accepted') {
            $this->askPerson($cashout, $source, __('injongepay::injongepay.cashout_rejected_after_unclear_send', ['status' => $cashout->bank_status]));

            return;
        }

        $this->fail($cashout, $source, __('injongepay::injongepay.cashout_bank_failed', ['status' => $cashout->bank_status]), $callId);
    }

    /** Two "not paid" answers count only when time has passed between them: pressing a button twice is one answer. */
    private function rejectedTwice(?string $previous, ?Carbon $previousAt): bool
    {
        return $previous === BankAnswers::FAILED && $previousAt?->lte(now()->subMinutes(self::REJECTED_APART_MINUTES));
    }

    /**
     * "Never heard of it" proves nothing: GTBank has timed out and paid later.
     * The money stays held and a person decides; it never comes back by itself.
     */
    private function notFound(Cashout $cashout, string $source, ?int $callId): void
    {
        $this->waitOrReview($cashout, $source);
    }

    private function inconclusive(Cashout $cashout, array $answer, string $source, ?int $callId): void
    {
        $d = $answer['details'];

        // An answer about our reference with another amount or account: a person must look.
        if ($d !== [] && ($d['reference'] === null || $d['reference'] === $cashout->reference)) {
            $this->items->open('cashout_mismatch', $cashout->reference, Wallet::query()->withoutGlobalScopes()->find($cashout->wallet_id), $cashout, $cashout->amount, ['answer' => $d, 'reason' => $answer['reason']]);
            $this->move($cashout, Cashout::NEEDS_REVIEW, $source, $answer['reason'], $d, $callId);

            return;
        }

        $this->waitOrReview($cashout, $source);
    }

    private function waitOrReview(Cashout $cashout, string $source): void
    {
        $since = $cashout->submitted_at ?? $cashout->created_at;

        if ($since->lt(now()->subMinutes(self::UNKNOWN_GRACE_MINUTES))) {
            $this->askPerson($cashout, $source, __('injongepay::injongepay.cashout_no_final_answer'));
        }
    }

    private function askPerson(Cashout $cashout, string $source, string $why): void
    {
        if ($cashout->status !== Cashout::NEEDS_REVIEW) {
            $this->move($cashout, Cashout::NEEDS_REVIEW, $source, $why);
            $this->items->open('cashout_unresolved', $cashout->reference, Wallet::query()->withoutGlobalScopes()->find($cashout->wallet_id), $cashout, $cashout->amount, ['last_answer' => $cashout->last_answer, 'checks' => $cashout->checks]);
            PlatformAlert::send('approve injongepay_platform.cashout', __('injongepay::injongepay.notify_cashout_review'),
                __('injongepay::injongepay.notify_cashout_review_body', ['ref' => $cashout->reference, 'amount' => Money::format($cashout->amount)]),
                route('injongepay.platform.cashouts.show', $cashout, false));
        }
    }

    private function complete(Cashout $cashout, string $source, ?string $note = null, ?int $callId = null): void
    {
        $cashout->forceFill(['completed_at' => now(), 'next_check_at' => null])->save();
        // Only now: until GTBank has paid, nothing says this money left.
        $this->markCoveredPayments($cashout);
        $this->move($cashout, Cashout::COMPLETED, $source, $note, [], $callId);

        audit('injongepay_cashout_completed', __('injongepay::injongepay.audit_cashout_completed', ['amount' => Money::format($cashout->amount), 'ref' => $cashout->reference]),
            $cashout, ['source' => $source, 'cbs_reference' => $cashout->cbs_reference, 'note' => $note], $cashout->business_id);
        $this->tellRequester($cashout, 'completed');
    }

    /** The money never left: it goes back to the wallet, with the payments it covered. */
    private function fail(Cashout $cashout, string $source, string $reason, ?int $callId = null): void
    {
        $wallet = Wallet::query()->withoutGlobalScopes()->findOrFail($cashout->wallet_id);
        $entry = $this->ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, $cashout->amount, 'cashout_reversal', 'cashout_reversal:'.$cashout->id, [
            'reference' => $cashout->reference.'R', 'source' => $cashout, 'description' => __('injongepay::injongepay.ledger_cashout_back', ['ref' => $cashout->reference]),
        ]);

        Payment::query()->withoutGlobalScopes()->where('cashout_id', $cashout->id)->update(['payment_status' => Payment::STATUS_AVAILABLE, 'cashout_id' => null]);
        $cashout->forceFill(['failed_at' => now(), 'failure_reason' => $reason, 'reversal_entry_id' => $entry->id, 'next_check_at' => now()->addMinutes(self::WATCH_EVERY_MINUTES)])->save();
        $this->move($cashout, Cashout::FAILED, $source, $reason, [], $callId);

        audit('injongepay_cashout_failed', __('injongepay::injongepay.audit_cashout_failed', ['amount' => Money::format($cashout->amount), 'ref' => $cashout->reference]),
            $cashout, ['source' => $source, 'reason' => $reason], $cashout->business_id);
        $this->tellRequester($cashout, 'failed');
    }

    /** Only the payments the amount fully covers, oldest first. */
    private function markCoveredPayments(Cashout $cashout): void
    {
        $left = $cashout->amount;

        foreach (Payment::query()->withoutGlobalScopes()->where('wallet_id', $cashout->wallet_id)->where('payment_status', Payment::STATUS_AVAILABLE)
            ->whereNotNull('cleared_at')->orderBy('id')->lockForUpdate()->get(['id', 'net_amount']) as $payment) {
            if ($payment->net_amount > $left) {
                break;
            }

            $left -= $payment->net_amount;
            Payment::query()->withoutGlobalScopes()->whereKey($payment->id)->update(['payment_status' => Payment::STATUS_CASHED_OUT, 'cashout_id' => $cashout->id]);
        }
    }

    private function move(Cashout $cashout, string $to, string $source, ?string $note = null, array $details = [], ?int $callId = null): void
    {
        $from = $cashout->getOriginal('status');

        if ($from !== $to) {
            $cashout->forceFill(['status' => $to])->save();
        }

        $this->event($cashout, $from, $to, $source, $note, $details, $callId);
    }

    private function event(Cashout $cashout, ?string $from, string $to, string $source, ?string $note, array $details = [], ?int $callId = null): void
    {
        CashoutEvent::query()->create([
            'cashout_id' => $cashout->id, 'from_status' => $from, 'to_status' => $to, 'source' => $source, 'note' => $note,
            'details' => $details ?: null, 'bank_call_id' => $callId, 'actor_id' => auth()->id(), 'actor_name' => auth()->user()?->name ?? __('injongepay::injongepay.actor_system'),
        ]);
    }

    private function tellRequester(Cashout $cashout, string $state): void
    {
        try {
            $requester = Approval::query()->withoutGlobalScopes()->find($cashout->approval_id)?->requested_by;

            if ($requester) {
                app(\App\Services\Notification\NotificationService::class)->notify($requester, __('injongepay::injongepay.notify_cashout_'.$state),
                    __('injongepay::injongepay.notify_cashout_'.$state.'_body', ['amount' => Money::format($cashout->amount), 'ref' => $cashout->reference]),
                    $state === 'completed' ? 'success' : 'warning', route('injongepay.cashouts.show', $cashout, false), $cashout);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
