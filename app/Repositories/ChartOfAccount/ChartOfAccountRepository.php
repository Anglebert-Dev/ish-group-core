<?php

namespace App\Repositories\ChartOfAccount;

use App\Enums\ChartOfAccountStatus;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountRepository extends BaseRepository
{
    public function __construct(ChartOfAccount $model)
    {
        parent::__construct($model);
    }

    public function findByCode(string $code): ?ChartOfAccount
    {
        return $this->query()->where('code', $code)->first();
    }

    public function postingAccounts(): Collection
    {
        return $this->query()
            ->where('is_posting', true)
            ->where('status', ChartOfAccountStatus::Active->value)
            ->orderBy('code')
            ->get();
    }

    public function expenseAccounts(): Collection
    {
        return $this->query()
            ->where('is_posting', true)
            ->where('account_type', 'expense')
            ->where('status', ChartOfAccountStatus::Active->value)
            ->orderBy('code')
            ->get();
    }
}
