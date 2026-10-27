<?php

namespace App\Repositories\Driver;

use App\Models\Driver\Driver;
use App\Repositories\BaseRepository;

class DriverRepository extends BaseRepository
{
    public function __construct(Driver $model)
    {
        parent::__construct($model);
    }
}
