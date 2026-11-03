<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only: written by the Ledger, never changed or deleted. A correction
 * is a new entry.
 */
class LedgerEntry extends Model
{
    use BelongsToBusiness, \Modules\InjongePay\Support\UuidRouteKey;

    public const UPDATED_AT = null;

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public const PENDING = 'pending';

    public const AVAILABLE = 'available';

    protected $table = 'ijp_ledger_entries';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            $entry->uuid ??= UuidLibrary::generate();
        });
        static::updating(fn () => throw new LogicException('Ledger entries are never changed.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are never deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'pending_after' => 'integer', 'available_after' => 'integer'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Signed effect on the wallet's total balance. */
    public function signedAmount(): int
    {
        return $this->direction === self::CREDIT ? $this->amount : -$this->amount;
    }
}
