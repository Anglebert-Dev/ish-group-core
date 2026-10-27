<?php

namespace App\Models\Driver;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentType;
use App\Enums\DriverStatus;
use App\Enums\LicenceCategory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends BaseModel
{
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'national_id',
        'license_number',
        'license_expiry',
        'license_category',
        'daily_rate',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'license_category' => LicenceCategory::class,
            'daily_rate' => 'decimal:2',
            'employment_status' => DriverEmploymentType::class,
            'availability_status' => DriverAvailabilityStatus::class,
            'status' => DriverStatus::class,
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function licenseAlertLevel(): \App\Enums\DocumentAlertLevel
    {
        return \App\Libraries\DocumentExpiryLibrary::level($this->license_expiry);
    }

    public function licenseAlertSummary(): string
    {
        return \App\Libraries\DocumentExpiryLibrary::summary($this->license_expiry);
    }
}
