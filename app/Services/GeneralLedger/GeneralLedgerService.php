<?php

namespace App\Services\GeneralLedger;

use App\Enums\GeneralLedgerStatus;
use App\Libraries\ListExport;
use App\Models\GeneralLedger\GeneralLedger;
use App\Repositories\GeneralLedger\GeneralLedgerRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GeneralLedgerService extends BaseService
{
    public function __construct(protected GeneralLedgerRepository $ledgers)
    {
    }

    public function list(Request $request): LengthAwarePaginator
    {
        return $this->filteredQuery($request)->paginate(20)->withQueryString();
    }

    public function exportList(Request $request): ListExport
    {
        $rows = $this->filteredQuery($request)
            ->get()
            ->map(fn (GeneralLedger $ledger) => [
                $ledger->transaction_date?->format('Y-m-d') ?? '—',
                $ledger->reference,
                $ledger->description,
                $ledger->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.general_ledger'), 'general-ledger', [
            __('body.date'),
            __('body.reference'),
            __('body.description'),
            __('body.status'),
        ], $rows);
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = $this->ledgers->query()
            ->with(['lines.account'])
            ->latest('transaction_date')
            ->latest('id');

        if ($request->filled('from')) {
            $query->whereDate('transaction_date', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('transaction_date', '<=', $request->string('to'));
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->string('reference_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('account_id')) {
            $accountId = (int) $request->input('account_id');
            $query->whereHas('lines', fn ($q) => $q->where('chart_of_account_id', $accountId));
        }

        return $query;
    }

    public function find(GeneralLedger $ledger): GeneralLedger
    {
        return $ledger->load(['lines.account', 'reversedLedger']);
    }

    public function periodTotals(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->toDateString();

        $lines = $this->ledgers->query()
            ->whereIn('status', [GeneralLedgerStatus::Posted->value, GeneralLedgerStatus::Reversed->value])
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->with('lines.account')
            ->get()
            ->flatMap->lines;

        $revenue = '0.00';
        $expense = '0.00';
        $bank = '0.00';
        $ar = '0.00';

        foreach ($lines as $line) {
            $code = $line->account?->code;
            $type = $line->account?->account_type?->value;
            $net = bcsub((string) $line->credit, (string) $line->debit, 2);

            if ($type === 'revenue') {
                $revenue = bcadd($revenue, $net, 2);
            }
            if ($type === 'expense') {
                $expense = bcadd($expense, bcsub((string) $line->debit, (string) $line->credit, 2), 2);
            }
            if ($code === '1.1.1') {
                $bank = bcadd($bank, bcsub((string) $line->debit, (string) $line->credit, 2), 2);
            }
            if ($code === '1.2.1') {
                $ar = bcadd($ar, bcsub((string) $line->debit, (string) $line->credit, 2), 2);
            }
        }

        return compact('from', 'to', 'revenue', 'expense', 'bank', 'ar');
    }
}
