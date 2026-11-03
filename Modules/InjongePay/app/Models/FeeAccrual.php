<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeAccrual extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ijp_fee_accruals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'swept_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
