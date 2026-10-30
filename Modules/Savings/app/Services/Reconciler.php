<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Entry;
use Modules\Savings\Models\Reconciliation;
use Modules\Savings\Support\Money;

/**
 * Statement balance vs ledger balance. A difference stays visible until it is
 * explained or resolved with an explained adjustment (BR-08); never hidden.
 */
class Reconciler
{
    public function __construct(protected Balances $balances, protected Operations $operations)
    {
    }

    public function check(Account $account, string $date, int $statementBalance): Reconciliation
    {
        $ledger = $this->balances->balance($account, $date);
        $rec = new Reconciliation([
            'account_id' => $account->id, 'statement_date' => $date, 'statement_balance' => $statementBalance,
            'ledger_balance' => $ledger, 'difference' => $statementBalance - $ledger,
            'status' => $statementBalance === $ledger ? 'matched' : 'open', 'user_id' => auth()->id(),
        ]);
        $rec->business_id = $account->business_id;
        $rec->save();

        audit('savings_reconciliation', __('savings::savings.audit_reconciled', [
            'account' => $account->name, 'difference' => Money::format($rec->difference, $account->currency),
        ]), $rec, ['statement' => $statementBalance, 'ledger' => $ledger], $account->business_id);

        return $rec;
    }

    /**
     * Note why the balances differ (e.g. pending card payment). The difference still shows.
     */
    public function explain(Reconciliation $rec, string $explanation): void
    {
        if (trim($explanation) === '' || $rec->status === 'matched') {
            throw new LedgerException(__('savings::savings.reason_required'));
        }

        $rec->forceFill(['status' => 'explained', 'explanation' => $explanation])->save();
    }

    /**
     * Post an explained adjustment for the difference (e.g. bank fees not recorded).
     */
    public function adjust(Reconciliation $rec, string $reason): void
    {
        if ($rec->difference === 0 || $rec->adjustment_transaction_id) {
            throw new LedgerException(__('savings::savings.nothing_to_adjust'));
        }

        $tx = $this->operations->adjust($rec->account, $rec->difference, $reason, [
            'effective_date' => $rec->statement_date->toDateString(),
            'description' => __('savings::savings.reconciliation_adjustment', ['date' => $rec->statement_date->format('d/m/Y')]),
            'idempotency_key' => 'reconcile:'.$rec->uuid,
        ]);

        $rec->forceFill(['status' => 'adjusted', 'explanation' => $reason, 'adjustment_transaction_id' => $tx->id])->save();
    }

    /**
     * Where to look: movements around the statement date and possible duplicates.
     *
     * @return array{nearby: Collection, duplicates: Collection}
     */
    public function suggestions(Reconciliation $rec): array
    {
        $date = Carbon::parse($rec->statement_date);
        $entries = Entry::query()->where('account_id', $rec->account_id)
            ->whereDate('effective_date', '>=', $date->copy()->subDays(45)->toDateString())->whereDate('effective_date', '<=', $date->copy()->addDays(5)->toDateString())
            ->with('transaction')->orderBy('effective_date')->get();

        $nearby = $entries->filter(fn (Entry $e) => abs($date->diffInDays($e->effective_date, false)) <= 5)->values();
        $duplicates = $entries->filter(fn (Entry $e) => ! $e->transaction?->isReversed() && ! $e->transaction?->isReversal())
            ->groupBy('amount')
            ->flatMap(fn ($group) => $group->count() > 1
                ? $group->filter(fn ($e) => $group->contains(fn ($o) => $o->id !== $e->id && abs($o->effective_date->diffInDays($e->effective_date)) <= 2))
                : [])
            ->values();
        $exact = $entries->filter(fn (Entry $e) => abs($e->amount) === abs($rec->difference))->values();

        return ['nearby' => $nearby, 'duplicates' => $duplicates, 'same_amount' => $exact];
    }
}
