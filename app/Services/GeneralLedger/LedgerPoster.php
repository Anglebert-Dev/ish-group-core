<?php

namespace App\Services\GeneralLedger;

use App\Enums\AccountType;
use App\Enums\GeneralLedgerStatus;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Models\GeneralLedger\GeneralLedger;
use App\Models\GeneralLedger\GeneralLedgerLine;
use App\Repositories\ChartOfAccount\ChartOfAccountRepository;
use App\Repositories\GeneralLedger\GeneralLedgerRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Throwable;

class LedgerPoster extends BaseService
{
    private const TOLERANCE = '0.01';

    public function __construct(
        protected GeneralLedgerRepository $ledgers,
        protected ChartOfAccountRepository $accounts,
    ) {
    }

    /**
     * @param  array<int, array{account_id:int,debit?:string|float,credit?:string|float,description?:string|null}>  $lines
     */
    public function post(
        string $reference,
        string $referenceType,
        ?int $referenceId,
        string $transactionDate,
        string $description,
        array $lines,
    ): array {
        try {
            return DB::transaction(function () use ($reference, $referenceType, $referenceId, $transactionDate, $description, $lines) {
                $existing = $this->ledgers->findByReference($reference);
                if ($existing && $existing->status === GeneralLedgerStatus::Posted) {
                    return $this->success($existing, __('body.ledger_already_posted'));
                }

                $normalized = $this->normalizeLines($lines);
                if ($normalized === null) {
                    return $this->failure(__('body.ledger_unbalanced'));
                }

                $ledger = new GeneralLedger([
                    'transaction_date' => $transactionDate,
                    'reference' => $reference,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => GeneralLedgerStatus::Posted->value,
                ]);
                $this->ledgers->save($ledger);

                foreach ($normalized as $line) {
                    $account = $this->accounts->find($line['account_id']);
                    if (! $account instanceof ChartOfAccount || ! $account->is_posting) {
                        throw new \RuntimeException(__('body.ledger_invalid_account'));
                    }

                    $entry = new GeneralLedgerLine([
                        'general_ledger_id' => $ledger->id,
                        'chart_of_account_id' => $account->id,
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'description' => $line['description'],
                    ]);
                    $entry->save();

                    $this->applyBalance($account, $line['debit'], $line['credit']);
                }

                return $this->success($ledger->load('lines.account'), __('body.ledger_posted'));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($e->getMessage() ?: __('body.something_went_wrong'));
        }
    }

    public function reverse(GeneralLedger $ledger, ?string $description = null): array
    {
        if ($ledger->status !== GeneralLedgerStatus::Posted) {
            return $this->failure(__('body.ledger_not_reversible'));
        }

        $ledger->loadMissing('lines');

        $reverseReference = 'REV-'.$ledger->reference;
        $lines = $ledger->lines->map(fn (GeneralLedgerLine $line) => [
            'account_id' => $line->chart_of_account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'description' => $line->description,
        ])->all();

        $result = $this->post(
            $reverseReference,
            $ledger->reference_type,
            $ledger->reference_id,
            now()->toDateString(),
            $description ?? __('body.ledger_reversal_of', ['reference' => $ledger->reference]),
            $lines,
        );

        if (! ($result['status'] ?? false)) {
            return $result;
        }

        $ledger->status = GeneralLedgerStatus::Reversed;
        $ledger->reversed_ledger_id = $result['data']->id;
        $this->ledgers->save($ledger);

        return $this->success($result['data'], __('body.ledger_reversed'));
    }

    public function reverseByReference(string $reference): array
    {
        $ledger = $this->ledgers->findByReference($reference);
        if (! $ledger || $ledger->status !== GeneralLedgerStatus::Posted) {
            return $this->success(null, __('body.ledger_nothing_to_reverse'));
        }

        return $this->reverse($ledger);
    }

    /**
     * @param  array<int, array{account_id:int,debit?:string|float,credit?:string|float,description?:string|null}>  $lines
     * @return array<int, array{account_id:int,debit:string,credit:string,description:?string}>|null
     */
    protected function normalizeLines(array $lines): ?array
    {
        $normalized = [];
        $debitTotal = '0.00';
        $creditTotal = '0.00';

        foreach ($lines as $line) {
            $debit = $this->money($line['debit'] ?? 0);
            $credit = $this->money($line['credit'] ?? 0);

            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                continue;
            }

            if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                return null;
            }

            $normalized[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];

            $debitTotal = bcadd($debitTotal, $debit, 2);
            $creditTotal = bcadd($creditTotal, $credit, 2);
        }

        if ($normalized === []) {
            return null;
        }

        $diff = abs((float) bcsub($debitTotal, $creditTotal, 2));
        if ($diff > (float) self::TOLERANCE) {
            return null;
        }

        return $normalized;
    }

    protected function applyBalance(ChartOfAccount $account, string $debit, string $credit): void
    {
        $delta = match ($account->account_type) {
            AccountType::Asset, AccountType::Expense => bcsub($debit, $credit, 2),
            default => bcsub($credit, $debit, 2),
        };

        $account->current_balance = bcadd((string) $account->current_balance, $delta, 2);
        $this->accounts->save($account);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
