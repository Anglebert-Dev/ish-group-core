<?php

namespace Modules\InjongePay\Gtbank;

use Modules\InjongePay\Support\Money;

/**
 * What a GTBank answer means for one of our disbursements. Strict on purpose:
 * money moves only on an answer that is about *our* reference, for *our*
 * amount and account, with a status that can only mean one thing.
 *
 *   completed     ACSC (or SUCCESS/COMPLETED)              → the money left
 *   failed        RJCT / CANC (or REJECTED/CANCELLED/…)    → it did not, and never will
 *   pending       PENDING / PROCESSING / …                 → ask again later
 *   not_found     GTBank has no such reference *right now* → never proof on its own:
 *                 a write that timed out can appear minutes later
 *   inconclusive  anything else: no answer, another reference, another
 *                 amount or account, a status we don't know
 */
final class BankAnswers
{
    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const NOT_FOUND = 'not_found';

    public const INCONCLUSIVE = 'inconclusive';

    private const COMPLETED_WORDS = ['ACSC', 'SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'COMPLETE', 'SETTLED'];

    private const FAILED_WORDS = ['RJCT', 'CANC', 'REJECTED', 'CANCELLED', 'CANCELED', 'DECLINED', 'REVERSED'];

    private const PENDING_WORDS = ['PENDING', 'PROCESSING', 'IN_PROGRESS', 'INPROGRESS', 'ACCEPTED', 'SUBMITTED', 'QUEUED', 'INITIATED', 'ACTC', 'ACSP', 'PDNG'];

    /**
     * @return array{state: string, reason: ?string, details: array}
     */
    public static function disbursement(BankResult $result, string $reference, int $amountCents, string $account): array
    {
        if ($result->notFound()) {
            return self::answer(self::NOT_FOUND, null, []);
        }

        if (! $result->ok()) {
            return self::answer(self::INCONCLUSIVE, $result->message(), []);
        }

        $d = BankPayloads::disbursement($result->data);

        if ($d['reference'] !== null && $d['reference'] !== $reference) {
            return self::answer(self::INCONCLUSIVE, __('injongepay::injongepay.answer_other_reference', ['got' => $d['reference']]), $d);
        }

        if ($d['amount'] !== null && ! self::sameAmount($d['amount'], $amountCents)) {
            return self::answer(self::INCONCLUSIVE, __('injongepay::injongepay.answer_other_amount', ['got' => $d['amount']]), $d);
        }

        if ($d['account'] !== null && GtbankClient::beneficiary($d['account']) !== GtbankClient::beneficiary($account)) {
            return self::answer(self::INCONCLUSIVE, __('injongepay::injongepay.answer_other_account', ['got' => $d['account']]), $d);
        }

        $status = strtoupper(str_replace([' ', '-'], '_', (string) $d['status']));

        return match (true) {
            in_array($status, self::COMPLETED_WORDS, true) => self::answer(self::COMPLETED, null, $d),
            in_array($status, self::FAILED_WORDS, true) => self::answer(self::FAILED, null, $d),
            in_array($status, self::PENDING_WORDS, true) => self::answer(self::PENDING, null, $d),
            // Not "FAILED": DigiPay also says FAILED when the *status query* failed.
            default => self::answer(self::INCONCLUSIVE, __('injongepay::injongepay.answer_unknown_status', ['status' => $d['status'] ?? '—']), $d),
        };
    }

    /** GTBank sends whole RWF (500), decimals (500.00) or strings ("500"). */
    public static function sameAmount(string $theirs, int $oursCents): bool
    {
        try {
            return Money::toCents(str_replace(',', '', trim($theirs))) === $oursCents;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function answer(string $state, ?string $reason, array $details): array
    {
        return ['state' => $state, 'reason' => $reason, 'details' => $details];
    }
}
