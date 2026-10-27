<?php

namespace Modules\Inventory\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Enums\ItemType;

/**
 * A product or material. Stock lives on its variants (sizes/colours);
 * an item without options has a single variant.
 */
class Item extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'inventory_items';

    protected $fillable = [
        'type', 'name', 'sku', 'category', 'unit', 'description',
        'option1_name', 'option2_name', 'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'reorder_level' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class)->orderBy('id');
    }

    public function hasOptions(): bool
    {
        return filled($this->option1_name) || filled($this->option2_name);
    }
}
