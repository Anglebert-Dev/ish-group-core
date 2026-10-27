<?php

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Extra\Extra;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExtra extends BaseModel
{
    protected $fillable = [
        'booking_id',
        'extra_id',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
