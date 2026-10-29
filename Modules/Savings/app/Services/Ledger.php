<?php

namespace Modules\Savings\Services;

use App\Libraries\UuidLibrary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Exceptions\LikelyDuplicate;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Entry;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\GoalAllocation;
use Modules\Savings\Models\Line;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\ObligationItem;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * The only way money moves. A transaction has entries (how each account
 * changed) and lines (what the money was for), and must balance:
 *
 *   sum(entries) = money-in lines − money-out lines
 *
 * A transfer has entries only, so it is never income or spending.
 * Posted transactions are never edited: reverse() and post a replacement.
 */
class Ledger
{
    /** Line natures that bring money in. */
    public const IN = ['income', 'expense_refund', 'receivable_down', 'payable_up', 'gain'];

    /** Line natures that take money out. */
    public const OUT = ['expense', 'income_refund', 'receivable_up', 'payable_down', 'loss'];

    public const TYPES = ['income', 'expense', 'transfer', 'refund', 'advance', 'repayment_in', 'borrowing', 'repayment_out', 'split', 'fee', 'interest', 'adjustment'];

    /**
     * @param  array{
     *   type: string, effective_date: string, description: string, idempotency_key: string,
     *   entries: list<array{account: Account, amount: int}>,
     *   lines?: list<array{nature: string, amount: int, category?: ?Category, obligation?: ?Obligation, note?: ?string}>,
     *   amount?: int, counterparty?: ?string, reference?: ?string, tags?: ?array, notes?: ?string, source?: string,
     *   original_amount?: ?int, original_currency?: ?string, exchange_rate?: ?string,
     *   refund_of?: ?Transaction, reverses?: ?Transaction, replaces?: ?Transaction,
     *   import_batch_id?: ?int, import_hash?: ?string, allow_duplicate?: bool, transfer_sent?: ?int
     * }  $spec
     *
     * @throws LedgerException|LikelyDuplicate
     */
    public function post(array $spec): Transaction
    {
        $first = $spec['entries'][0]['account'] ?? null;
        $businessId = $first?->business_id;

        if ($businessId && ($existing = Transaction::query()->withoutGlobalScope('business')
            ->where('business_id', $businessId)->where('idempotency_key', $spec['idempotency_key'])->first())) {
            return $existing; // Same operation submitted again (BR-10).
        }

        $this->validate($spec);
        $lines = $spec['lines'] ?? [];
        $currency = $spec['currency'] ?? $first->currency;

        if (empty($spec['allow_duplicate']) && ! in_array($spec['source'] ?? 'manual', ['reversal', 'restore', 'expected'], true)) {
            $matches = $this->likelyDuplicates($spec);
            if ($matches->isNotEmpty()) {
                throw new LikelyDuplicate($matches);
            }
        }

        $tx = DB::transaction(function () use ($spec, $lines, $currency, $businessId) {
            $tx = new Transaction;
            $tx->forceFill([
                'business_id' => $businessId,
                'type' => $spec['type'],
                'effective_date' => $spec['effective_date'],
                'description' => mb_substr($spec['description'], 0, 255),
                'counterparty' => $spec['counterparty'] ?? null,
                'reference' => $spec['reference'] ?? null,
                'amount' => $spec['amount'] ?? $this->displayAmount($spec),
                'currency' => $currency,
                'original_amount' => $spec['original_amount'] ?? null,
                'original_currency' => $spec['original_currency'] ?? null,
                'exchange_rate' => $spec['exchange_rate'] ?? null,
                'tags' => $spec['tags'] ?? null,
                'notes' => $spec['notes'] ?? null,
                'idempotency_key' => $spec['idempotency_key'],
                'source' => $spec['source'] ?? 'manual',
                'reverses_id' => $spec['reverses']?->id ?? null,
                'replaces_id' => $spec['replaces']?->id ?? null,
                'refund_of_id' => $spec['refund_of']?->id ?? null,
                'import_batch_id' => $spec['import_batch_id'] ?? null,
                'import_hash' => $spec['import_hash'] ?? null,
                'user_id' => Auth::id(),
            ])->save();

            foreach ($spec['entries'] as $entry) {
                (new Entry)->forceFill([
                    'business_id' => $businessId, 'transaction_id' => $tx->id, 'account_id' => $entry['account']->id,
                    'amount' => $entry['amount'], 'effective_date' => $tx->effective_date,
                ])->save();
            }

            foreach ($lines as $line) {
                (new Line)->forceFill([
                    'business_id' => $businessId, 'transaction_id' => $tx->id, 'nature' => $line['nature'],
                    'category_id' => ($line['category'] ?? null)?->id, 'obligation_id' => ($line['obligation'] ?? null)?->id,
                    'amount' => $line['amount'], 'effective_date' => $tx->effective_date, 'note' => $line['note'] ?? null,
                ])->save();
            }

            return $tx;
        });

        if (($spec['source'] ?? 'manual') !== 'restore') {
            audit('savings_'.$tx->type, __('savings::savings.audit_posted', [
                'type' => __('savings::savings.type_'.$tx->type), 'amount' => Money::format($tx->amount, $tx->currency), 'description' => $tx->description,
            ]), $tx, ['entries' => collect($spec['entries'])->map(fn ($e) => [$e['account']->name => $e['amount']])->all()], $businessId);
        }

        return $tx;
    }

    /**
     * Mirror a posted transaction (same date, opposite amounts). The original stays, marked reversed.
     */
    public function reverse(Transaction $tx, string $reason): Transaction
    {
        if ($tx->isReversed() || $tx->isReversal()) {
            throw new LedgerException(__('savings::savings.cannot_reverse'));
        }

        if (trim($reason) === '') {
            throw new LedgerException(__('savings::savings.reason_required'));
        }

        return DB::transaction(function () use ($tx, $reason) {
            $tx->load(['entries.account', 'lines.category']);
            $obligations = Obligation::query()->withoutGlobalScope('business')->whereIn('id', $tx->lines->pluck('obligation_id')->filter())->get()->keyBy('id');

            $reversal = $this->post([
                'type' => $tx->type,
                'effective_date' => $tx->effective_date->toDateString(),
                'description' => __('savings::savings.reversal_of', ['description' => $tx->description]),
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'counterparty' => $tx->counterparty,
                'reference' => $tx->reference,
                'idempotency_key' => 'reverse:'.$tx->uuid,
                'source' => 'reversal',
                'reverses' => $tx,
                'notes' => $reason,
                'entries' => $tx->entries->map(fn (Entry $e) => ['account' => $e->account, 'amount' => -$e->amount])->all(),
                'lines' => $tx->lines->map(fn (Line $l) => [
                    'nature' => $l->nature, 'amount' => -$l->amount, 'category' => $l->category,
                    'obligation' => $l->obligation_id ? $obligations[$l->obligation_id] : null, 'note' => $l->note,
                ])->all(),
                'skip_validation' => true,
            ]);

            $tx->forceFill(['reversed_at' => now(), 'reversal_reason' => mb_substr($reason, 0, 500)])->save();

            // Undo what other records took from it.
            ObligationItem::query()->withoutGlobalScope('business')->where('transaction_id', $tx->id)->whereIn('type', ['basis', 'settlement'])->get()
                ->each(fn (ObligationItem $item) => (new ObligationItem)->forceFill([
                    'business_id' => $item->business_id, 'obligation_id' => $item->obligation_id, 'type' => 'reversal',
                    'amount' => $item->type === 'basis' ? -$item->amount : $item->amount,
                    'transaction_id' => $reversal->id, 'note' => $reason, 'effective_date' => $tx->effective_date, 'user_id' => Auth::id(),
                ])->save());
            GoalAllocation::query()->withoutGlobalScope('business')->where('transaction_id', $tx->id)->where('kind', 'contribution')->get()
                ->each(fn (GoalAllocation $a) => (new GoalAllocation)->forceFill([
                    'business_id' => $a->business_id, 'goal_id' => $a->goal_id, 'kind' => 'contribution', 'amount' => -$a->amount,
                    'transaction_id' => $reversal->id, 'effective_date' => $tx->effective_date, 'note' => $reason, 'user_id' => Auth::id(),
                ])->save());
            Expected::query()->withoutGlobalScope('business')->where('transaction_id', $tx->id)->get()
                ->each(fn (Expected $e) => $e->forceFill(['status' => 'expected', 'transaction_id' => null])->save());
            Commitment::query()->withoutGlobalScope('business')->where('transaction_id', $tx->id)->get()
                ->each(fn (Commitment $c) => $c->forceFill(['status' => 'open', 'transaction_id' => null])->save());

            audit('savings_reversal', __('savings::savings.audit_reversed', ['description' => $tx->description, 'reason' => $reason]), $tx, ['reason' => $reason], $tx->business_id);

            return $reversal;
        });
    }

    /**
     * Correct = reverse the original and post the replacement, linked (BR-09).
     */
    public function correct(Transaction $tx, string $reason, array $replacement): Transaction
    {
        return DB::transaction(function () use ($tx, $reason, $replacement) {
            $this->reverse($tx, $reason);

            return $this->post($replacement + ['replaces' => $tx, 'allow_duplicate' => true]);
        });
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function likelyDuplicates(array $spec): Collection
    {
        $date = \Illuminate\Support\Carbon::parse($spec['effective_date']);
        $accounts = collect($spec['entries'])->map(fn ($e) => [$e['account']->id, $e['amount']]);

        return Transaction::query()->withoutGlobalScope('business')
            ->where('business_id', $spec['entries'][0]['account']->business_id)
            ->whereNull('reversed_at')->whereNull('reverses_id')
            ->whereBetween('effective_date', [$date->copy()->subDays(2)->toDateString(), $date->copy()->addDays(2)->toDateString()])
            ->when(filled($spec['reference'] ?? null), fn ($q) => $q->where(fn ($w) => $w->whereNull('reference')->orWhere('reference', $spec['reference'])))
            ->whereHas('entries', function ($q) use ($accounts) {
                $q->where(function ($w) use ($accounts) {
                    foreach ($accounts as [$accountId, $amount]) {
                        $w->orWhere(fn ($x) => $x->where('account_id', $accountId)->where('amount', $amount));
                    }
                });
            })
            ->limit(5)->get();
    }

    private function validate(array $spec): void
    {
        if (! empty($spec['skip_validation'])) {
            return; // reversals mirror an already valid transaction
        }

        if (! in_array($spec['type'] ?? null, self::TYPES, true)) {
            throw new LedgerException(__('savings::savings.type_invalid'));
        }

        $entries = $spec['entries'] ?? [];
        $lines = $spec['lines'] ?? [];

        if ($entries === []) {
            throw new LedgerException(__('savings::savings.no_account'));
        }

        $businessId = $entries[0]['account']->business_id;
        $currency = $spec['currency'] ?? $entries[0]['account']->currency;

        foreach ($entries as $entry) {
            $account = $entry['account'];
            if (! $account instanceof Account || $account->business_id !== $businessId) {
                throw new LedgerException(__('savings::savings.account_invalid'));
            }
            if ($account->isArchived()) {
                throw new LedgerException(__('savings::savings.account_archived', ['name' => $account->name]));
            }
            if (! is_int($entry['amount']) || $entry['amount'] === 0) {
                throw new LedgerException(__('savings::savings.amount_invalid'));
            }
            if ($spec['effective_date'] < $account->opening_date->toDateString()) {
                throw new LedgerException(__('savings::savings.before_opening', ['name' => $account->name, 'date' => $account->opening_date->format('d/m/Y')]));
            }
        }

        foreach ($lines as $line) {
            $nature = $line['nature'];
            if (! in_array($nature, [...self::IN, ...self::OUT], true) || ! is_int($line['amount']) || $line['amount'] <= 0) {
                throw new LedgerException(__('savings::savings.line_invalid'));
            }
            $category = $line['category'] ?? null;
            $needs = match (true) {
                in_array($nature, ['income', 'income_refund'], true) => 'income',
                in_array($nature, ['expense', 'expense_refund'], true) => 'expense',
                default => null,
            };
            if ($needs && (! $category || $category->kind !== $needs || $category->business_id !== $businessId)) {
                throw new LedgerException(__('savings::savings.category_required', ['kind' => __('savings::savings.kind_'.$needs)]));
            }
            if (str_starts_with($nature, 'receivable') || str_starts_with($nature, 'payable')) {
                $obligation = $line['obligation'] ?? null;
                $direction = str_starts_with($nature, 'receivable') ? 'receivable' : 'payable';
                if (! $obligation || $obligation->direction !== $direction || $obligation->business_id !== $businessId) {
                    throw new LedgerException(__('savings::savings.obligation_required'));
                }
            }
        }

        if ($spec['type'] === 'transfer') {
            if (count($entries) < 2 || collect($lines)->contains(fn ($l) => $l['nature'] !== 'expense')) {
                throw new LedgerException(__('savings::savings.transfer_invalid'));
            }
        }

        $inOut = collect($lines)->sum(fn ($l) => in_array($l['nature'], self::IN, true) ? $l['amount'] : -$l['amount']);
        $currencies = collect($entries)->map(fn ($e) => $e['account']->currency)->unique();

        if ($currencies->count() === 1 && $currencies->first() === $currency) {
            if (array_sum(array_column($entries, 'amount')) !== $inOut) {
                throw new LedgerException(__('savings::savings.unbalanced'));
            }

            return;
        }

        // Different currencies: only a transfer, with the rate and what was sent (BR-11).
        if ($spec['type'] !== 'transfer' || count($entries) !== 2 || blank($spec['exchange_rate'] ?? null) || ! isset($spec['transfer_sent'])) {
            throw new LedgerException(__('savings::savings.fx_invalid'));
        }

        [$from, $to] = $entries[0]['amount'] < 0 ? $entries : array_reverse($entries);
        $sent = (int) $spec['transfer_sent'];
        $expected = Money::convert($sent, $from['account']->currency, $to['account']->currency, (string) $spec['exchange_rate']);

        if ($from['account']->currency !== $currency || $from['amount'] !== -$sent + $inOut || abs($to['amount'] - $expected) > 1) {
            throw new LedgerException(__('savings::savings.unbalanced'));
        }
    }

    private function displayAmount(array $spec): int
    {
        $lines = collect($spec['lines'] ?? [])->sum('amount');

        return $lines > 0 ? $lines : (int) collect($spec['entries'])->max(fn ($e) => abs($e['amount']));
    }

    public static function key(): string
    {
        return UuidLibrary::generate();
    }
}
