<?php

namespace Modules\SalesOrders\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Variant;

class SalesOrderLine extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'delivered_quantity' => 'float', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function remaining(): float
    {
        return max(0, round($this->quantity - $this->delivered_quantity, 3));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }
}
