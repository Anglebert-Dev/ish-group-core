<?php

namespace Modules\Billing\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\InvoiceReason;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Support\Money;

class Invoice extends BaseModel
{
    protected $table = 'billing_invoices';

    protected $fillable = [
        'number', 'business_id', 'subscription_id', 'reason', 'package_id', 'status',
        'currency', 'subtotal', 'discount', 'credit', 'credit_from_balance', 'proration_excess', 'tax_rate', 'tax_inclusive',
        'tax_label', 'tax', 'total', 'amount_paid', 'period_start', 'period_end',
        'issued_at', 'due_at', 'paid_at', 'voided_at', 'billed_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reason' => InvoiceReason::class,
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'credit' => 'decimal:2',
            'credit_from_balance' => 'decimal:2',
            'proration_excess' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_inclusive' => 'boolean',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'billed_to' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function name(): string
    {
        return $this->number;
    }

    public function balanceCents(): int
    {
        return max(0, Money::toCents($this->total) - Money::toCents($this->amount_paid));
    }

    public function pendingCents(): int
    {
        return Money::toCents($this->payments()->where('status', PaymentStatus::Pending->value)->sum('amount'));
    }

    public function isOpen(): bool
    {
        return $this->status === InvoiceStatus::Open;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at && $this->due_at->isPast();
    }
}
