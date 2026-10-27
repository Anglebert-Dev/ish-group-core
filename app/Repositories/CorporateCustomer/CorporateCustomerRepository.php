<?php

namespace App\Repositories\CorporateCustomer;

use App\Models\CorporateCustomer\CorporateCustomer;
use App\Repositories\BaseRepository;

class CorporateCustomerRepository extends BaseRepository
{
    public function __construct(CorporateCustomer $model)
    {
        parent::__construct($model);
    }
}
