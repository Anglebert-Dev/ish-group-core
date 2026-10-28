<?php

namespace Modules\Purchasing\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Variant;

class PurchaseOrderLine extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'received_quantity' => 'float', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function remaining(): float
    {
        return max(0, round($this->quantity - $this->received_quantity, 3));
    }
}
