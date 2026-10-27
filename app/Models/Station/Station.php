<?php

namespace App\Models\Station;

use App\Enums\StationStatus;
use App\Models\BaseModel;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'opening_time',
        'closing_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StationStatus::class,
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
