<?php

namespace App\Services\Expense;

use App\Enums\ExpenseStatus;
use App\Enums\SupplierType;
use App\Libraries\ListExport;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Models\Expense\Expense;
use App\Models\Supplier\Supplier;
use App\Repositories\ChartOfAccount\ChartOfAccountRepository;
use App\Repositories\Expense\ExpenseRepository;
use App\Services\AccountAssignment\AccountAssignmentService;
use App\Services\BaseService;
use App\Services\GeneralLedger\LedgerPoster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpenseService extends BaseService
{
    public function __construct(
        protected ExpenseRepository $expenses,
        protected ChartOfAccountRepository $accounts,
        protected AccountAssignmentService $assignments,
        protected LedgerPoster $poster,
    ) {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->expenses->query()
            ->with(['supplier', 'account'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->expenses->query()
            ->with(['supplier', 'account'])
            ->latest('expense_date')
            ->latest('id')
            ->get()
            ->map(fn (Expense $expense) => [
                $expense->expense_number,
                $expense->expense_date?->format('Y-m-d') ?? '—',
                $expense->supplier?->name ?? '—',
                $expense->account?->code ?? '—',
                number_format((float) $expense->amount, 0).' RWF',
                $expense->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.expenses'), 'expenses', [
            __('body.number'),
            __('body.date'),
            __('body.supplier'),
            __('body.account'),
            __('body.amount'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $accountId = $data['chart_of_account_id'] ?? null;
                if (! $accountId && ! empty($data['supplier_id'])) {
                    $supplier = Supplier::query()->find($data['supplier_id']);
                    $accountId = $this->defaultExpenseAccountId($supplier?->type)?->id;
                }
                if (! $accountId) {
                    $accountId = $this->assignments->requireAccount('expense_post', 'expense')->id;
                }

                $expense = new Expense([
                    'expense_number' => $this->nextNumber(),
                    'expense_date' => $data['expense_date'],
                    'amount' => $data['amount'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'chart_of_account_id' => $accountId,
                    'description' => $data['description'] ?? null,
                    'paid' => (bool) ($data['paid'] ?? false),
                    'status' => ExpenseStatus::Draft->value,
                    'notes' => $data['notes'] ?? null,
                ]);
                $this->expenses->save($expense);

                if (! empty($data['post_now'])) {
                    $post = $this->post($expense);
                    if (! ($post['status'] ?? false)) {
                        throw new \RuntimeException($post['message'] ?? __('body.something_went_wrong'));
                    }
                    $expense = $post['data'];
                }

                return $this->success($expense, __('body.expense_created'));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($e->getMessage() ?: __('body.something_went_wrong'));
        }
    }

    public function update(Expense $expense, array $data): array
    {
        if ($expense->status !== ExpenseStatus::Draft) {
            return $this->failure(__('body.expense_not_editable'));
        }

        $expense->fill($data);
        $this->expenses->save($expense);

        return $this->success($expense, __('body.expense_updated'));
    }

    public function post(Expense $expense): array
    {
        if ($expense->status === ExpenseStatus::Posted) {
            return $this->success($expense, __('body.expense_already_posted'));
        }

        $amount = (string) $expense->amount;
        $expenseAccount = $expense->account ?? $this->accounts->find($expense->chart_of_account_id);

        if (! $expenseAccount instanceof ChartOfAccount) {
            return $this->failure(__('body.ledger_invalid_account'));
        }

        $creditAccount = $expense->paid
            ? $this->assignments->requireAccount('expense_post', 'cash')
            : $this->assignments->requireAccount('expense_post', 'payable');

        if (! $expense->paid && ! $expense->supplier_id) {
            return $this->failure(__('body.expense_supplier_required_unpaid'));
        }

        $result = $this->poster->post(
            'EXP-'.$expense->uuid,
            'expense_post',
            $expense->id,
            $expense->expense_date->toDateString(),
            $expense->description ?: ('Expense '.$expense->expense_number),
            [
                ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount],
            ],
        );

        if (! ($result['status'] ?? false)) {
            return $result;
        }

        $expense->status = ExpenseStatus::Posted;
        $this->expenses->save($expense);

        return $this->success($expense->fresh(['supplier', 'account']), __('body.expense_posted'));
    }

    public function delete(Expense $expense): array
    {
        if ($expense->status === ExpenseStatus::Posted) {
            return $this->failure(__('body.expense_cannot_delete_posted'));
        }

        $expense->delete();

        return $this->success(null, __('body.expense_deleted'));
    }

    public function defaultExpenseAccountId(?SupplierType $type): ?ChartOfAccount
    {
        $code = match ($type) {
            SupplierType::Parts => '5.1.1',
            SupplierType::Fuel => '5.1.2',
            SupplierType::Insurance => '5.1.3',
            SupplierType::Services => '5.1.4',
            default => '5.1.5',
        };

        return $this->accounts->findByCode($code);
    }

    protected function nextNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Ymd').'-';
        $last = $this->expenses->query()
            ->where('expense_number', 'like', $prefix.'%')
            ->orderByDesc('expense_number')
            ->value('expense_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
