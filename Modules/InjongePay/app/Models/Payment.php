<?php

namespace Modules\InjongePay\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsActivity;
use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credit GTBank told us about. Never deleted; the bank reversing it is a
 * status, plus a ledger entry.
 */
class Payment extends Model
{
    use BelongsToBusiness, LogsActivity;

    public const STATUS_AVAILABLE = 'AVAILABLE';

    public const STATUS_CASHED_OUT = 'CASHED_OUT';

    public const STATUS_REVERSED = 'REVERSED';

    public const VIA_WEBHOOK = 'WEBHOOK';

    public const VIA_BANK_PULL = 'BANK_PULL';

    public const VIA_MIGRATION = 'MIGRATION';

    protected $table = 'ijp_payments';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            $payment->uuid ??= UuidLibrary::generate();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer', 'fee_amount' => 'integer', 'bank_cut_amount' => 'integer',
            'platform_revenue_amount' => 'integer', 'net_amount' => 'integer',
            'fee_rate' => 'decimal:2', 'value_date' => 'date', 'cleared_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isCleared(): bool
    {
        return $this->cleared_at !== null;
    }
}
