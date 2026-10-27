<?php

namespace Modules\Inventory\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sellable/stockable version of an item, e.g. "M / Blue".
 */
class Variant extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'inventory_variants';

    protected $fillable = ['option1', 'option2', 'sku', 'cost_price', 'sale_price', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'reorder_level' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * "M / Blue", or empty for an item without options.
     */
    public function optionLabel(): string
    {
        return collect([$this->option1, $this->option2])->filter(fn ($v) => filled($v))->implode(' / ');
    }

    /**
     * "School shirt (M / Blue)".
     */
    public function displayName(): string
    {
        $item = $this->item?->name ?? '';
        $options = $this->optionLabel();

        return $options === '' ? $item : "{$item} ({$options})";
    }

    public function reorderLevel(): ?float
    {
        return $this->reorder_level ?? $this->item?->reorder_level;
    }
}
