<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;

class FeeRateChange extends Model
{
    public const UPDATED_AT = null;

    public const GLOBAL_FEE = 'global_fee';

    public const GLOBAL_BANK_CUT = 'global_bank_cut';

    public const BUSINESS_FEE = 'business_fee';

    protected $table = 'ijp_fee_rate_changes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_value' => 'decimal:4', 'new_value' => 'decimal:4', 'created_at' => 'datetime'];
    }
}
