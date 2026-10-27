<?php

namespace App\Repositories\Supplier;

use App\Models\Supplier\Supplier;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }

    public function paginateList(): LengthAwarePaginator
    {
        return $this->query()->orderBy('name')->paginate(15);
    }
}
