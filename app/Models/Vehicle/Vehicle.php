<?php

namespace App\Models\Vehicle;

use App\Enums\FuelType;
use App\Enums\LicenceCategory;
use App\Enums\TransmissionType;
use App\Enums\VehicleOwnershipType;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Libraries\RwandaPlateLibrary;
use App\Models\BaseModel;
use App\Models\Booking\Booking;
use App\Models\Station\Station;
use App\Models\VehicleCategory\VehicleCategory;
use App\Models\FuelLog\FuelLog;
use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Models\VehicleOwner\VehicleOwner;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends BaseModel
{
    protected $fillable = [
        'make',
        'model',
        'year',
        'plate_number',
        'vin',
        'color',
        'vehicle_category_id',
        'daily_price',
        'station_id',
        'vehicle_owner_id',
        'vehicle_type',
        'seats',
        'transmission',
        'fuel_type',
        'engine_capacity',
        'weight',
        'mileage',
        'required_license_category',
        'notes',
        'insurance_company',
        'insurance_expires_at',
        'insurance_document',
        'inspection_done_at',
        'inspection_expires_at',
        'inspection_document',
        'carte_jaune_number',
        'carte_jaune_issued_at',
        'carte_jaune_document',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'transmission' => TransmissionType::class,
            'fuel_type' => FuelType::class,
            'required_license_category' => LicenceCategory::class,
            'status' => VehicleStatus::class,
            'daily_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'insurance_expires_at' => 'date',
            'inspection_done_at' => 'date',
            'inspection_expires_at' => 'date',
            'carte_jaune_issued_at' => 'date',
        ];
    }

    protected function plateNumber(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }

                return RwandaPlateLibrary::normalize($value) ?? strtoupper(trim($value));
            },
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'assigned_vehicle_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    public function coverImage(): ?VehicleImage
    {
        return $this->images->first();
    }

    public function coverImageUrl(): ?string
    {
        return $this->coverImage()?->url();
    }

    public function name(): string
    {
        return "{$this->make} {$this->model} ({$this->year})";
    }

    public function ownershipType(): VehicleOwnershipType
    {
        return $this->vehicle_owner_id
            ? VehicleOwnershipType::Outsider
            : VehicleOwnershipType::Company;
    }

    public function isCompanyOwned(): bool
    {
        return $this->ownershipType() === VehicleOwnershipType::Company;
    }

    public function ownershipLabel(): string
    {
        if ($this->owner) {
            return $this->owner->fullName();
        }

        return VehicleOwnershipType::Company->label();
    }

    /**
     * Each vehicle has its own daily rental price.
     * Category daily_price is only a suggested default when creating a vehicle.
     */
    public function dailyPrice(): ?string
    {
        if ($this->daily_price !== null && $this->daily_price !== '') {
            return (string) $this->daily_price;
        }

        return $this->category?->daily_price !== null
            ? (string) $this->category->daily_price
            : null;
    }

    public function insuranceAlertLevel(): \App\Enums\DocumentAlertLevel
    {
        return \App\Libraries\DocumentExpiryLibrary::level($this->insurance_expires_at);
    }

    public function insuranceAlertSummary(): string
    {
        return \App\Libraries\DocumentExpiryLibrary::summary($this->insurance_expires_at);
    }

    public function inspectionAlertLevel(): \App\Enums\DocumentAlertLevel
    {
        return \App\Libraries\DocumentExpiryLibrary::level($this->inspection_expires_at);
    }

    public function inspectionAlertSummary(): string
    {
        return \App\Libraries\DocumentExpiryLibrary::summary($this->inspection_expires_at);
    }

    public function documentUrl(?string $path): ?string
    {
        return $path ? \App\Libraries\UploadLibrary::url($path) : null;
    }
}
