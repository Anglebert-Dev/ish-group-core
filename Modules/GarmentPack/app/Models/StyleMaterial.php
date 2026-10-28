<?php

namespace Modules\GarmentPack\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Variant;

class StyleMaterial extends Model
{
    use BelongsToBusiness;

    protected $table = 'garment_style_materials';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'waste_percent' => 'float'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }

    /**
     * Quantity per piece including waste.
     */
    public function perPiece(): float
    {
        return round($this->quantity * (1 + $this->waste_percent / 100), 4);
    }
}
