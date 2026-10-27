<?php

namespace App\Repositories\Station;

use App\Models\Station\Station;
use App\Repositories\BaseRepository;

class StationRepository extends BaseRepository
{
    public function __construct(Station $model)
    {
        parent::__construct($model);
    }
}
