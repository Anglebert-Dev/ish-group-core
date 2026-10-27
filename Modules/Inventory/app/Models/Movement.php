<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use Modules\Inventory\Enums\MovementType;

/**
 * One line of the movement log. Append-only: never updated or deleted;
 * a mistake is corrected with a new movement.
 */
class Movement extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $table = 'inventory_movements';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'float',
            'balance_after' => 'float',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Stock movements cannot be deleted.'));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
