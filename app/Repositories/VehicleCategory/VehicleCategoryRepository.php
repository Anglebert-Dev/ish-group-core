<?php

namespace App\Repositories\VehicleCategory;

use App\Models\VehicleCategory\VehicleCategory;
use App\Repositories\BaseRepository;

class VehicleCategoryRepository extends BaseRepository
{
    public function __construct(VehicleCategory $model)
    {
        parent::__construct($model);
    }
}
