<?php

namespace App\Models\Vehicle;

use App\Libraries\UploadLibrary;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleImage extends BaseModel
{
    protected $fillable = [
        'vehicle_id',
        'file_path',
        'sort_order',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function url(): string
    {
        return UploadLibrary::url($this->file_path);
    }
}
