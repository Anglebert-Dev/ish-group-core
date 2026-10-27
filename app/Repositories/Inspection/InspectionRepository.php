<?php

namespace App\Repositories\Inspection;

use App\Models\Inspection\Inspection;
use App\Repositories\BaseRepository;

class InspectionRepository extends BaseRepository
{
    public function __construct(Inspection $model)
    {
        parent::__construct($model);
    }
}
