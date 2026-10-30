<?php

namespace Modules\Savings\Models;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Modules\Savings\Support\BelongsToOwner;
use Modules\Savings\Support\HasPublicId;

class Transaction extends Model
{
    use BelongsToBusiness, BelongsToOwner, HasPublicId;

    protected $table = 'sav_transactions';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'amount' => 'integer', 'original_amount' => 'integer', 'tags' => 'array', 'reversed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Posted transactions are never edited: only the reversal link may be set.
        static::updating(function (self $tx) {
            $allowed = ['reversed_at', 'reversal_reason', 'updated_at'];
            if (array_diff(array_keys($tx->getDirty()), $allowed) !== []) {
                throw new \LogicException('Posted transactions cannot be changed; reverse and replace them.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Posted transactions cannot be deleted; reverse them.'));
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Line::class);
    }

    public function reverses(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function refundOf(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }
}
