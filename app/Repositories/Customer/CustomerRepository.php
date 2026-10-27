<?php

namespace App\Repositories\Customer;

use App\Models\Customer\Customer;
use App\Repositories\BaseRepository;

class CustomerRepository extends BaseRepository
{
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->query()->where('email', strtolower(trim($email)))->first();
    }
}
