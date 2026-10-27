<?php

namespace App\Models\FuelLog;

use App\Enums\FuelLogStatus;
use App\Enums\FuelType;
use App\Models\BaseModel;
use App\Models\Driver\Driver;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends BaseModel
{
    protected $fillable = [
        'vehicle_id',
        'gas_station_name',
        'driver_id',
        'fuel_type',
        'date',
        'liters',
        'cost',
        'mileage',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'liters' => 'decimal:2',
            'cost' => 'decimal:2',
            'fuel_type' => FuelType::class,
            'status' => FuelLogStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
