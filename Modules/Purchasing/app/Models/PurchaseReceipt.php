<?php

namespace Modules\Purchasing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['received_on', 'supplier_reference', 'note'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'value' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }
}
