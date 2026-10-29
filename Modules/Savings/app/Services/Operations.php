<?php

namespace Modules\Savings\Services;

use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Line;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * Named financial operations, each turned into a balanced ledger transaction.
 * $meta carries effective_date, description, counterparty, reference, tags,
 * notes, idempotency_key, allow_duplicate, source, expected-event links…
 */
class Operations
{
    public function __construct(protected Ledger $ledger)
    {
    }

    public function income(Account $account, int $amount, Category $category, array $meta): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'income') + [
            'entries' => [['account' => $account, 'amount' => $amount]],
            'lines' => [['nature' => 'income', 'amount' => $amount, 'category' => $category]],
        ]);
    }

    /**
     * An expense, optionally paid in another currency (original amount + rate kept).
     */
    public function expense(Account $account, int $amount, Category $category, array $meta, string $type = 'expense'): Transaction
    {
        return $this->ledger->post($this->meta($meta, $type) + [
            'entries' => [['account' => $account, 'amount' => -$amount]],
            'lines' => [['nature' => 'expense', 'amount' => $amount, 'category' => $category]],
        ]);
    }

    /**
     * Money moved between own accounts. Never income or spending (BR-02).
     * Different currencies: $received in the destination currency at $rate. Optional fee (an expense).
     */
    public function transfer(Account $from, Account $to, int $amount, array $meta, ?int $received = null, ?string $rate = null, int $fee = 0, ?Category $feeCategory = null): Transaction
    {
        if ($from->is($to)) {
            throw new LedgerException(__('savings::savings.transfer_same'));
        }

        $sameCurrency = $from->currency === $to->currency;
        $received = $sameCurrency ? $amount : ($received ?? ($rate ? Money::convert($amount, $from->currency, $to->currency, $rate) : null));

        if ($received === null) {
            throw new LedgerException(__('savings::savings.fx_invalid'));
        }

        if (! $sameCurrency && ! $rate) {
            $rate = bcdiv(bcmul((string) $received, bcpow('10', (string) Money::decimals($from->currency)), 12), bcmul((string) $amount, bcpow('10', (string) Money::decimals($to->currency)), 12), 10);
        }

        return $this->ledger->post($this->meta($meta, 'transfer') + [
            'amount' => $amount,
            'currency' => $from->currency,
            'exchange_rate' => $sameCurrency ? null : $rate,
            'original_amount' => $sameCurrency ? null : $received,
            'original_currency' => $sameCurrency ? null : $to->currency,
            'transfer_sent' => $amount,
            'entries' => [['account' => $from, 'amount' => -($amount + $fee)], ['account' => $to, 'amount' => $received]],
            'lines' => $fee > 0 ? [['nature' => 'expense', 'amount' => $fee, 'category' => $feeCategory, 'note' => __('savings::savings.transfer_fee')]] : [],
        ]);
    }

    /**
     * Money back for an earlier transaction, linked to it (BR-04). Reduces that category's spending (or income).
     */
    public function refund(Transaction $original, Account $account, int $amount, array $meta): Transaction
    {
        $line = $original->lines()->whereIn('nature', ['expense', 'income'])->with('category')->first();

        if (! $line) {
            throw new LedgerException(__('savings::savings.refund_invalid'));
        }

        $refunded = (int) Transaction::query()->where('refund_of_id', $original->id)->whereNull('reversed_at')->sum('amount');
        if ($refunded + $amount > $line->amount) {
            throw new LedgerException(__('savings::savings.refund_too_much', ['max' => Money::format($line->amount - $refunded, $original->currency)]));
        }

        $isExpense = $line->nature === 'expense';

        return $this->ledger->post($this->meta($meta, 'refund') + [
            'refund_of' => $original,
            'entries' => [['account' => $account, 'amount' => $isExpense ? $amount : -$amount]],
            'lines' => [['nature' => $isExpense ? 'expense_refund' : 'income_refund', 'amount' => $amount, 'category' => $line->category]],
        ]);
    }

    /**
     * One payment that is partly a real expense and partly money to be paid back
     * (e.g. paid 250,600 by mistake instead of 25,600: 225,000 is owed back).
     */
    public function splitPayment(Account $account, int $expense, Category $category, int $owedBack, Obligation $receivable, array $meta): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'split') + [
            'amount' => $expense + $owedBack,
            'entries' => [['account' => $account, 'amount' => -($expense + $owedBack)]],
            'lines' => array_values(array_filter([
                $expense > 0 ? ['nature' => 'expense', 'amount' => $expense, 'category' => $category] : null,
                ['nature' => 'receivable_up', 'amount' => $owedBack, 'obligation' => $receivable],
            ])),
        ]);
    }

    /**
     * Money lent or advanced: leaves the account, becomes owed to us. Not spending.
     */
    public function lend(Account $account, int $amount, Obligation $receivable, array $meta): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'advance') + [
            'entries' => [['account' => $account, 'amount' => -$amount]],
            'lines' => [['nature' => 'receivable_up', 'amount' => $amount, 'obligation' => $receivable]],
        ]);
    }

    /**
     * Money owed to us comes back: the account goes up, the receivable goes down. Not income (BR-03).
     */
    public function receiveRepayment(Obligation $receivable, Account $account, int $amount, array $meta): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'repayment_in') + [
            'entries' => [['account' => $account, 'amount' => $amount]],
            'lines' => [['nature' => 'receivable_down', 'amount' => $amount, 'obligation' => $receivable]],
        ]);
    }

    /**
     * Money borrowed: comes in, becomes a debt. Not income.
     */
    public function borrow(Account $account, int $amount, Obligation $payable, array $meta): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'borrowing') + [
            'entries' => [['account' => $account, 'amount' => $amount]],
            'lines' => [['nature' => 'payable_up', 'amount' => $amount, 'obligation' => $payable]],
        ]);
    }

    /**
     * Paying a debt: principal reduces the debt; interest and fees are expenses (FR-09).
     */
    public function repayDebt(Obligation $payable, Account $account, int $principal, array $meta, int $interest = 0, ?Category $interestCategory = null): Transaction
    {
        return $this->ledger->post($this->meta($meta, 'repayment_out') + [
            'amount' => $principal + $interest,
            'entries' => [['account' => $account, 'amount' => -($principal + $interest)]],
            'lines' => array_values(array_filter([
                ['nature' => 'payable_down', 'amount' => $principal, 'obligation' => $payable],
                $interest > 0 ? ['nature' => 'expense', 'amount' => $interest, 'category' => $interestCategory, 'note' => __('savings::savings.interest')] : null,
            ])),
        ]);
    }

    /**
     * An explained correction of an account (e.g. bank fee found at reconciliation). Reason required.
     */
    public function adjust(Account $account, int $signedAmount, string $reason, array $meta): Transaction
    {
        if (trim($reason) === '' || $signedAmount === 0) {
            throw new LedgerException(__('savings::savings.reason_required'));
        }

        return $this->ledger->post($this->meta($meta + ['notes' => $reason], 'adjustment') + [
            'amount' => abs($signedAmount),
            'entries' => [['account' => $account, 'amount' => $signedAmount]],
            'lines' => [['nature' => $signedAmount > 0 ? 'gain' : 'loss', 'amount' => abs($signedAmount), 'note' => $reason]],
        ]);
    }

    private function meta(array $meta, string $type): array
    {
        return array_merge([
            'type' => $type,
            'effective_date' => now()->toDateString(),
            'description' => __('savings::savings.type_'.$type),
            'idempotency_key' => Ledger::key(),
        ], array_filter($meta, fn ($v) => $v !== null));
    }
}
