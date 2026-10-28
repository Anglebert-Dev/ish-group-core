<?php

namespace Modules\Delivery\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SalesOrders\Models\SalesOrder;

class DeliveryNote extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['delivered_on', 'receiver_name', 'receiver_phone', 'signature', 'vehicle', 'notes'];

    protected $hidden = ['signature'];

    protected function casts(): array
    {
        return ['delivered_on' => 'date'];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }
}
