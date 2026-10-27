<?php

namespace App\Models\VehicleOwner;

use App\Enums\VehicleOwnerStatus;
use App\Models\BaseModel;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleOwner extends BaseModel
{
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'national_id',
        'notes',
        'owner_share_percent',
        'company_share_percent',
        'agreement_starts_on',
        'agreement_ends_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'owner_share_percent' => 'decimal:2',
            'company_share_percent' => 'decimal:2',
            'agreement_starts_on' => 'date',
            'agreement_ends_on' => 'date',
            'status' => VehicleOwnerStatus::class,
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function splitLabel(): string
    {
        return number_format((float) $this->owner_share_percent, 0).'% / '
            .number_format((float) $this->company_share_percent, 0).'%';
    }
}
