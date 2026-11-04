<?php

namespace Modules\InjongePay\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One merchant code at GTBank (a till, branch or collection point). Balances
 * are written by the Ledger only.
 */
class Wallet extends BaseModel
{
    use BelongsToBusiness;

    public const BANK_NOT_REGISTERED = 'NOT_REGISTERED';

    public const BANK_REGISTERING = 'REGISTERING';

    public const BANK_PENDING = 'PENDING';

    public const BANK_ACTIVE = 'ACTIVE';

    public const BANK_INACTIVE = 'INACTIVE';

    public const BANK_CLOSED = 'CLOSED';

    public const BANK_FAILED = 'FAILED';

    public const ROLE_BANK_CUT = 'bank_cut';

    public const ROLE_PLATFORM_REVENUE = 'platform_revenue';

    protected $table = 'ijp_wallets';

    protected $guarded = ['id', 'uuid', 'business_id', 'merchant_code', 'pending_balance', 'available_balance', 'internal_role', 'legacy_id', 'frozen_at', 'frozen_reason'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'pending_balance' => 'integer',
            'available_balance' => 'integer',
            'bank_synced_at' => 'datetime',
            'bank_missing_since' => 'datetime',
            'frozen_at' => 'datetime',
            'bank_error_at' => 'datetime',
            'bank_update_pending_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isInternal(): bool
    {
        return $this->internal_role !== null;
    }

    /** Takes payments: switched on here and live at GTBank (internal wallets never take payments). */
    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function acceptsPayments(bool $bankConfigured = true): bool
    {
        return $this->is_enabled && ! $this->isInternal()
            && (! $bankConfigured || $this->bank_status === self::BANK_ACTIVE);
    }

    public function balance(): int
    {
        return $this->pending_balance + $this->available_balance;
    }

    /** "Main till / 55500001": how the wallet appears in bank logs. */
    public function label(): string
    {
        return $this->name.' / '.$this->merchant_code;
    }
}
