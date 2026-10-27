<?php

namespace App\Models\VehicleMaintenance;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\BaseModel;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends BaseModel
{
    protected $fillable = [
        'vehicle_id',
        'maintenance_type',
        'description',
        'date',
        'cost',
        'mileage',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost' => 'decimal:2',
            'maintenance_type' => MaintenanceType::class,
            'status' => MaintenanceStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
