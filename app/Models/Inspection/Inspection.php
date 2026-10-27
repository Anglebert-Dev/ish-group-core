<?php

namespace App\Models\Inspection;

use App\Enums\FuelLevel;
use App\Enums\InspectionType;
use App\Models\BaseModel;
use App\Models\Booking\Booking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends BaseModel
{
    protected $fillable = [
        'booking_id',
        'type',
        'mileage',
        'fuel_level',
        'condition_notes',
        'deposit_amount',
        'damage_charges',
    ];

    protected function casts(): array
    {
        return [
            'type' => InspectionType::class,
            'fuel_level' => FuelLevel::class,
            'deposit_amount' => 'decimal:2',
            'damage_charges' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class)->orderBy('sort_order');
    }
}
