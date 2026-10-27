<?php

namespace App\Repositories\Vehicle;

use App\Models\Vehicle\Vehicle;
use App\Repositories\BaseRepository;

class VehicleRepository extends BaseRepository
{
    public function __construct(Vehicle $model)
    {
        parent::__construct($model);
    }
}
