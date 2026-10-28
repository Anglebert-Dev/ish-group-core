<?php

namespace Modules\GarmentPack\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Item;

class Style extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'garment_styles';

    protected $fillable = ['code', 'name', 'item_id', 'description', 'labour_cost', 'overhead_cost', 'is_active'];

    protected function casts(): array
    {
        return ['labour_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(StyleMaterial::class)->orderBy('id');
    }
}
