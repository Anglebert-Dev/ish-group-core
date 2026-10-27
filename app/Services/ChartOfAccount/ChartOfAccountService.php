<?php

namespace App\Services\ChartOfAccount;

use App\Enums\AccountType;
use App\Enums\ChartOfAccountStatus;
use App\Libraries\ListExport;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Repositories\ChartOfAccount\ChartOfAccountRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountService extends BaseService
{
    public function __construct(protected ChartOfAccountRepository $accounts)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->accounts->query()
            ->with('parent')
            ->orderBy('code')
            ->paginate(50);
    }

    public function exportList(): ListExport
    {
        $rows = $this->accounts->query()
            ->orderBy('code')
            ->get()
            ->map(fn (ChartOfAccount $account) => [
                $account->code,
                $account->name,
                $account->account_type->label(),
                $account->is_posting ? __('body.yes') : __('body.no'),
                number_format((float) $account->current_balance, 0).' RWF',
                $account->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.chart_of_accounts'), 'chart-of-accounts', [
            __('body.account_code'),
            __('body.account_name'),
            __('body.account_type'),
            __('body.posting'),
            __('body.balance'),
            __('body.status'),
        ], $rows);
    }

    public function allOrdered(): Collection
    {
        return $this->accounts->query()->orderBy('code')->get();
    }

    public function postingOptions(): Collection
    {
        return $this->accounts->postingAccounts();
    }

    public function expenseOptions(): Collection
    {
        return $this->accounts->expenseAccounts();
    }

    public function create(array $data): array
    {
        $type = AccountType::from($data['account_type']);
        $account = new ChartOfAccount([
            ...$data,
            'normal_balance' => $type->normalBalance()->value,
            'is_posting' => (bool) ($data['is_posting'] ?? true),
            'status' => $data['status'] ?? ChartOfAccountStatus::Active->value,
            'current_balance' => 0,
        ]);

        $this->accounts->save($account);

        return $this->success($account, __('body.chart_of_account_created'));
    }

    public function update(ChartOfAccount $account, array $data): array
    {
        if (! $account->is_posting && array_key_exists('is_posting', $data) && $data['is_posting']) {
            // allow promoting summary only intentionally
        }

        if (isset($data['account_type'])) {
            $type = AccountType::from($data['account_type']);
            $data['normal_balance'] = $type->normalBalance()->value;
        }

        $account->fill($data);
        $this->accounts->save($account);

        return $this->success($account, __('body.chart_of_account_updated'));
    }


    public function updateStatus(ChartOfAccount $account, ChartOfAccountStatus $status): array
    {
        $account->status = $status;

        $this->accounts->save($account);

        return $this->success($account, __('body.chart_of_account_status_updated'));
    }

    public function delete(ChartOfAccount $account): array
    {
        if (bccomp((string) $account->current_balance, '0', 2) !== 0) {
            return $this->failure(__('body.chart_of_account_has_balance'));
        }

        $account->delete();

        return $this->success(null, __('body.chart_of_account_deleted'));
    }
}
