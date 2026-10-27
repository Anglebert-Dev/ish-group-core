<?php

namespace Modules\Frontend\Repositories;

use App\Repositories\BaseRepository;
use Modules\Frontend\Models\Customer;

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
