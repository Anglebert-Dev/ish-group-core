<?php

namespace App\Models\Booking;

use App\Enums\BookingStatus;
use App\Enums\InspectionType;
use App\Enums\LicenceCategory;
use App\Enums\PaymentOption;
use App\Enums\PickupMethod;
use App\Enums\RentalType;
use App\Enums\ReturnMethod;
use App\Models\BaseModel;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use App\Models\Driver\Driver;
use App\Models\Inspection\Inspection;
use App\Models\Invoice\Invoice;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends BaseModel
{
    protected $fillable = [
        'customer_id',
        'corporate_customer_id',
        'pickup_station_id',
        'return_station_id',
        'pickup_method',
        'return_method',
        'pickup_at',
        'return_at',
        'rental_days',
        'vehicle_category_id',
        'category_daily_price',
        'extras_total',
        'chauffeur_fee',
        'delivery_fee',
        'collection_fee',
        'delivery_address',
        'collection_address',
        'rental_type',
        'driver_license_number',
        'driver_license_category',
        'payment_option',
        'total_amount',
        'notes',
        'assigned_vehicle_id',
        'assigned_driver_id',
    ];

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'actual_return_at' => 'datetime',
            'category_daily_price' => 'decimal:2',
            'extras_total' => 'decimal:2',
            'chauffeur_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'collection_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'extra_days_charge' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'pickup_method' => PickupMethod::class,
            'return_method' => ReturnMethod::class,
            'rental_type' => RentalType::class,
            'driver_license_category' => LicenceCategory::class,
            'payment_option' => PaymentOption::class,
            'status' => BookingStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function corporateCustomer(): BelongsTo
    {
        return $this->belongsTo(CorporateCustomer::class);
    }

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'pickup_station_id');
    }

    public function returnStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'return_station_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function assignedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function pickupInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->where('type', InspectionType::Pickup->value);
    }

    public function returnInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->where('type', InspectionType::Return->value);
    }

    public function isCorporate(): bool
    {
        return $this->corporate_customer_id !== null;
    }

    public function isPayLater(): bool
    {
        return $this->payment_option?->isPayLater() ?? false;
    }

    public function customerName(): string
    {
        return $this->isCorporate()
            ? (string) $this->corporateCustomer?->company_name
            : (string) $this->customer?->name;
    }
}
