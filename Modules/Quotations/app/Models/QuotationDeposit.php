<?php

namespace Modules\Quotations\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationDeposit extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['paid_on', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
