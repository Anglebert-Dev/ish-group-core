<?php

namespace Modules\Purchasing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Store;
use Modules\Purchasing\Enums\PurchaseStatus;

class PurchaseOrder extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['supplier_id', 'store_id', 'order_date', 'expected_date', 'notes'];

    protected function casts(): array
    {
        return ['status' => PurchaseStatus::class, 'order_date' => 'date', 'expected_date' => 'date', 'total' => 'decimal:2'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class)->latest('id');
    }
}
