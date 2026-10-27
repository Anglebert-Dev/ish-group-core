<?php

namespace App\Repositories\Business;

use App\Models\Business\Business;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BusinessRepository extends BaseRepository
{
    public function __construct(Business $model)
    {
        parent::__construct($model);
    }

    public function paginateWithOwner(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with('owner')
            ->withCount('users')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function first(): ?Business
    {
        return $this->query()->orderBy('id')->first();
    }
}
