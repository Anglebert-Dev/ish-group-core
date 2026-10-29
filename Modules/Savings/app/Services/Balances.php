<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Collection;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Entry;

/**
 * Actual balances, always computed from the opening balance and posted entries (BR-01).
 */
class Balances
{
    public function balance(Account $account, ?string $asOf = null): int
    {
        if ($asOf !== null && $asOf < $account->opening_date->toDateString()) {
            return 0;
        }

        return $account->opening_balance + (int) Entry::query()->withoutGlobalScope('business')
            ->where('account_id', $account->id)
            ->when($asOf, fn ($q) => $q->where('effective_date', '<=', $asOf))
            ->sum('amount');
    }

    /**
     * [account_id => balance] for many accounts in one query.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, int>
     */
    public function many(Collection $accounts, ?string $asOf = null): array
    {
        $sums = Entry::query()->withoutGlobalScope('business')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->when($asOf, fn ($q) => $q->where('effective_date', '<=', $asOf))
            ->selectRaw('account_id, SUM(amount) as total')->groupBy('account_id')->pluck('total', 'account_id');

        return $accounts->mapWithKeys(fn (Account $a) => [
            $a->id => ($asOf !== null && $asOf < $a->opening_date->toDateString()) ? 0 : $a->opening_balance + (int) ($sums[$a->id] ?? 0),
        ])->all();
    }
}
