<?php

namespace App\Models\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\BaseModel;
use App\Models\Booking\Booking;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'customer_id',
        'corporate_customer_id',
        'booking_id',
        'subtotal',
        'total',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function partyName(): string
    {
        if ($this->corporate_customer_id) {
            return (string) $this->corporateCustomer?->company_name;
        }

        return (string) $this->customer?->name;
    }
}
