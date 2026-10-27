<?php

namespace App\Services\FinancialStatement;

use App\Enums\AccountType;
use App\Enums\GeneralLedgerStatus;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Models\GeneralLedger\GeneralLedgerLine;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FinancialStatementService extends BaseService
{
    public function trialBalance(?string $from, ?string $to): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->toDateString();
        $movements = $this->movements($from, $to);
        $closingMoves = $this->movements(null, $to);

        $rows = $this->postingAccounts()->map(function (ChartOfAccount $account) use ($movements, $closingMoves) {
            $move = $movements->get($account->id, ['debit' => '0.00', 'credit' => '0.00']);
            $closeMove = $closingMoves->get($account->id, ['debit' => '0.00', 'credit' => '0.00']);
            $closing = $this->closingBalance($account, $closeMove['debit'], $closeMove['credit']);

            return [
                'account' => $account,
                'debit' => $move['debit'],
                'credit' => $move['credit'],
                'closing' => $closing,
            ];
        })->filter(fn ($row) => bccomp($row['debit'], '0', 2) !== 0
            || bccomp($row['credit'], '0', 2) !== 0
            || bccomp($row['closing'], '0', 2) !== 0
        )->values();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total_debit' => $rows->reduce(fn ($c, $r) => bcadd($c, $r['debit'], 2), '0.00'),
            'total_credit' => $rows->reduce(fn ($c, $r) => bcadd($c, $r['credit'], 2), '0.00'),
        ];
    }

    public function profitAndLoss(?string $from, ?string $to): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->toDateString();
        $movements = $this->movements($from, $to);

        $revenue = [];
        $expenses = [];
        $revenueTotal = '0.00';
        $expenseTotal = '0.00';

        foreach ($this->postingAccounts() as $account) {
            $move = $movements->get($account->id, ['debit' => '0.00', 'credit' => '0.00']);

            if ($account->account_type === AccountType::Revenue) {
                $amount = bcsub($move['credit'], $move['debit'], 2);
                if (bccomp($amount, '0', 2) === 0) {
                    continue;
                }
                $revenue[] = ['account' => $account, 'amount' => $amount];
                $revenueTotal = bcadd($revenueTotal, $amount, 2);
            }

            if ($account->account_type === AccountType::Expense) {
                $amount = bcsub($move['debit'], $move['credit'], 2);
                if (bccomp($amount, '0', 2) === 0) {
                    continue;
                }
                $expenses[] = ['account' => $account, 'amount' => $amount];
                $expenseTotal = bcadd($expenseTotal, $amount, 2);
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'net_income' => bcsub($revenueTotal, $expenseTotal, 2),
        ];
    }

    public function balanceSheet(?string $asOf): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $movements = $this->movements(null, $asOf);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $assetTotal = '0.00';
        $liabilityTotal = '0.00';
        $equityTotal = '0.00';

        foreach ($this->postingAccounts() as $account) {
            $move = $movements->get($account->id, ['debit' => '0.00', 'credit' => '0.00']);
            $closing = $this->closingBalance($account, $move['debit'], $move['credit']);

            if (bccomp($closing, '0', 2) === 0) {
                continue;
            }

            $row = ['account' => $account, 'amount' => $closing];

            if ($account->account_type === AccountType::Asset) {
                $assets[] = $row;
                $assetTotal = bcadd($assetTotal, $closing, 2);
            } elseif ($account->account_type === AccountType::Liability) {
                $liabilities[] = $row;
                $liabilityTotal = bcadd($liabilityTotal, $closing, 2);
            } elseif ($account->account_type === AccountType::Equity) {
                $equity[] = $row;
                $equityTotal = bcadd($equityTotal, $closing, 2);
            }
        }

        $pl = $this->profitAndLoss(null, $asOf);
        $retained = $pl['net_income'];
        $equityTotal = bcadd($equityTotal, $retained, 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_earnings' => $retained,
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
        ];
    }

    protected function postingAccounts(): Collection
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return collect();
        }

        return ChartOfAccount::query()
            ->where('is_posting', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, array{debit:string,credit:string}>
     */
    protected function movements(?string $from, string $to): Collection
    {
        if (! Schema::hasTable('general_ledger_lines')) {
            return collect();
        }

        $rows = GeneralLedgerLine::query()
            ->selectRaw('chart_of_account_id, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->whereHas('ledger', function ($q) use ($from, $to) {
                $q->whereIn('status', [
                    GeneralLedgerStatus::Posted->value,
                    GeneralLedgerStatus::Reversed->value,
                ])->whereDate('transaction_date', '<=', $to);
                if ($from) {
                    $q->whereDate('transaction_date', '>=', $from);
                }
            })
            ->groupBy('chart_of_account_id')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->chart_of_account_id => [
                'debit' => number_format((float) $row->debit_total, 2, '.', ''),
                'credit' => number_format((float) $row->credit_total, 2, '.', ''),
            ],
        ]);
    }

    protected function closingBalance(ChartOfAccount $account, string $debit, string $credit): string
    {
        return match ($account->account_type) {
            AccountType::Asset, AccountType::Expense => bcsub($debit, $credit, 2),
            default => bcsub($credit, $debit, 2),
        };
    }
}
