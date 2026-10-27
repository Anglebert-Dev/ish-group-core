<?php

namespace App\Repositories\VehicleMaintenance;

use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Repositories\BaseRepository;

class VehicleMaintenanceRepository extends BaseRepository
{
    public function __construct(VehicleMaintenance $model)
    {
        parent::__construct($model);
    }
}
