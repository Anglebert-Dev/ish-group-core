<?php

namespace Modules\Purchasing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['paid_on', 'amount', 'method', 'reference', 'note'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
