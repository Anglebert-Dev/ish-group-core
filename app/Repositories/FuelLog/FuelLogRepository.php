<?php

namespace App\Repositories\FuelLog;

use App\Models\FuelLog\FuelLog;
use App\Repositories\BaseRepository;

class FuelLogRepository extends BaseRepository
{
    public function __construct(FuelLog $model)
    {
        parent::__construct($model);
    }
}
