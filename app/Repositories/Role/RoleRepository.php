<?php

namespace App\Repositories\Role;

use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function allForStaffGuard(): Collection
    {
        return $this->query()
            ->where('guard_name', 'staff')
            ->withCount('permissions')
            ->orderBy('name')
            ->get();
    }

    public function find(int|string $id): ?Role
    {
        return $this->query()->where('guard_name', 'staff')->find($id);
    }
}
