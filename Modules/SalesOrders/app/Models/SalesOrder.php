<?php

namespace Modules\SalesOrders\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Store;
use Modules\SalesOrders\Enums\OrderStatus;

class SalesOrder extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['customer_id', 'store_id', 'order_date', 'due_date', 'customer_reference', 'discount', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'order_id')->orderBy('position');
    }

    public function isEditable(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday()
            && ! in_array($this->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true);
    }
}
