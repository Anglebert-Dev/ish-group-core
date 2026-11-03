<?php

namespace Modules\InjongePay\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class SettlementAccount extends BaseModel
{
    use BelongsToBusiness;

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const APPROVED = 'approved';

    public const DISABLED = 'disabled';

    protected $table = 'ijp_settlement_accounts';

    protected $guarded = ['id', 'uuid', 'business_id', 'status', 'verified_names', 'verified_at', 'approved_by', 'approved_at', 'usable_from', 'legacy_id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'approved_at' => 'datetime', 'usable_from' => 'datetime'];
    }

    /** Approved, and past its cooling-off. */
    public function isUsable(): bool
    {
        return $this->status === self::APPROVED && $this->usable_from !== null && $this->usable_from->isPast();
    }

    public function label(): string
    {
        return trim(($this->bank_name ? $this->bank_name.' · ' : '').$this->account.' · '.$this->holder_name);
    }
}
