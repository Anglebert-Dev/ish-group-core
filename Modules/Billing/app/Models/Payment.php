<?php

namespace Modules\Billing\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;

class Payment extends BaseModel
{
    protected $table = 'billing_payments';

    protected $fillable = [
        'business_id', 'invoice_id', 'amount', 'method', 'reference', 'paid_on',
        'proof_path', 'status', 'notes', 'submitted_by', 'reviewed_by', 'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function name(): string
    {
        return __('billing::billing.payment_for', ['number' => $this->invoice?->number]);
    }
}
