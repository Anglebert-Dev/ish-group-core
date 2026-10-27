<?php

namespace App\Repositories\Role;

use App\Models\Role\Role;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class RoleRepository extends BaseRepository
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function forBusiness(?int $businessId): Builder
    {
        return $this->query()
            ->where('guard_name', 'staff')
            ->where('business_id', $businessId)
            ->orderByDesc('is_default')
            ->orderBy('name');
    }
}
