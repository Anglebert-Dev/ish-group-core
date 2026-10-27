<?php

namespace App\Models\Driver;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends BaseModel
{
    protected $fillable = [
        'driver_id',
        'type',
        'original_name',
        'file_path',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
