<?php

namespace Modules\Billing\Models;

use App\Models\Business\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice details and credit balance of one business.
 */
class BillingAccount extends Model
{
    protected $table = 'billing_accounts';

    protected $fillable = ['business_id', 'billing_name', 'billing_email', 'tax_id', 'address', 'credit_balance'];

    protected function casts(): array
    {
        return ['credit_balance' => 'decimal:2'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
