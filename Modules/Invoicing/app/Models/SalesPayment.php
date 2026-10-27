<?php

namespace Modules\Invoicing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Invoicing\Enums\PaymentMethod;

/**
 * Money received against an invoice. Never edited; a mistake is reversed (with a reason).
 */
class SalesPayment extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['paid_on', 'amount', 'method', 'reference', 'note'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
