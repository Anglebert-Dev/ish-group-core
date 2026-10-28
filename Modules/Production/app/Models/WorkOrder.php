<?php

namespace Modules\Production\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Production\Enums\WorkOrderStatus;

class WorkOrder extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'production_work_orders';

    protected $fillable = ['variant_id', 'store_id', 'sales_order_id', 'quantity', 'line', 'daily_target', 'start_date', 'due_date', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'quantity' => 'float',
            'rejected' => 'float',
            'daily_target' => 'float',
            'stages' => 'array',
            'counts' => 'array',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProductionEntry::class)->latest('id');
    }

    public function salesOrder(): ?BelongsTo
    {
        return class_exists(\Modules\SalesOrders\Models\SalesOrder::class)
            ? $this->belongsTo(\Modules\SalesOrders\Models\SalesOrder::class, 'sales_order_id')
            : null;
    }

    public function lastStage(): int
    {
        return count($this->stages) - 1;
    }

    public function count(int $stage): float
    {
        return (float) ($this->counts[$stage] ?? 0);
    }

    public function finished(): float
    {
        return $this->count($this->lastStage());
    }

    public function progressPercent(): int
    {
        return $this->quantity > 0 ? (int) min(100, round($this->finished() / $this->quantity * 100)) : 0;
    }
}
