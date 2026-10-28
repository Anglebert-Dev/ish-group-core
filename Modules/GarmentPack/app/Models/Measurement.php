<?php

namespace Modules\GarmentPack\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Measurement extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'garment_measurements';

    protected $fillable = ['customer_id', 'name', 'values', 'taken_on', 'notes'];

    protected function casts(): array
    {
        return ['values' => 'array', 'taken_on' => 'date'];
    }
}
