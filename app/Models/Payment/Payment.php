<?php

namespace App\Models\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BaseModel;
use App\Models\Booking\Booking;
use App\Models\Expense\Expense;
use App\Models\Invoice\Invoice;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends BaseModel
{
    protected $fillable = [
        'payment_number',
        'payment_date',
        'amount',
        'method',
        'booking_id',
        'invoice_id',
        'expense_id',
        'supplier_id',
        'status',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
