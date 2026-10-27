<?php

namespace App\Repositories\AccountAssignment;

use App\Models\AccountAssignment\AccountAssignment;
use App\Repositories\BaseRepository;

class AccountAssignmentRepository extends BaseRepository
{
    public function __construct(AccountAssignment $model)
    {
        parent::__construct($model);
    }

    public function findByActionSlot(string $action, string $slot): ?AccountAssignment
    {
        return $this->query()
            ->with('account')
            ->where('action', $action)
            ->where('slot', $slot)
            ->first();
    }
}
