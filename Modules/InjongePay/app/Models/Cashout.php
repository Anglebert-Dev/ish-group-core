<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cashout extends Model
{
    use BelongsToBusiness, \Modules\InjongePay\Support\UuidRouteKey;

    public const QUEUED = 'QUEUED';

    public const SUBMITTING = 'SUBMITTING';

    public const SUBMITTED = 'SUBMITTED';

    public const UNKNOWN = 'UNKNOWN';

    public const NEEDS_REVIEW = 'NEEDS_REVIEW';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const OPEN = [self::QUEUED, self::SUBMITTING, self::SUBMITTED, self::UNKNOWN, self::NEEDS_REVIEW];

    protected $table = 'ijp_cashouts';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'submitted_at' => 'datetime', 'webhook_at' => 'datetime', 'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CashoutEvent::class)->orderBy('id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }

    public function label(): string
    {
        return $this->reference;
    }
}
