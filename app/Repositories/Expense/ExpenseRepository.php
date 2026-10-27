<?php

namespace App\Repositories\Expense;

use App\Models\Expense\Expense;
use App\Repositories\BaseRepository;

class ExpenseRepository extends BaseRepository
{
    public function __construct(Expense $model)
    {
        parent::__construct($model);
    }
}
