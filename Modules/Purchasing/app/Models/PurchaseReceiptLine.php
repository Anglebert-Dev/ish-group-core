<?php

namespace Modules\Purchasing\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Variant;

class PurchaseReceiptLine extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'decimal:2'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }
}
