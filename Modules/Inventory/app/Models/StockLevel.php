<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-hand quantity of one variant in one store. Written only by StockService,
 * together with the movement that explains it.
 */
class StockLevel extends Model
{
    use BelongsToBusiness;

    protected $table = 'inventory_stock';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
