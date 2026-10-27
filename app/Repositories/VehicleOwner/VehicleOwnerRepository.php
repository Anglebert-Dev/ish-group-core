<?php

namespace App\Repositories\VehicleOwner;

use App\Enums\VehicleOwnerStatus;
use App\Models\VehicleOwner\VehicleOwner;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VehicleOwnerRepository extends BaseRepository
{
    public function __construct(VehicleOwner $model)
    {
        parent::__construct($model);
    }

    public function paginateList(): LengthAwarePaginator
    {
        return $this->query()
            ->withCount('vehicles')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15);
    }

    public function catalog(): Collection
    {
        return $this->query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function activeCatalog(): Collection
    {
        return $this->query()
            ->where('status', VehicleOwnerStatus::Active->value)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
