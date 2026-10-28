<?php

namespace Modules\GarmentPack\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Worker extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'garment_workers';

    protected $fillable = ['name', 'phone', 'line', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
