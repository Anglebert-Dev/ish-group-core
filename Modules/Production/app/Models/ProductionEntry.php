<?php

namespace Modules\Production\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One count entered on a work order. Append-only.
 */
class ProductionEntry extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'count_after' => 'float', 'work_date' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Production entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Production entries cannot be deleted.'));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
