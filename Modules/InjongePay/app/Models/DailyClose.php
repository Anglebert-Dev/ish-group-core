<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClose extends Model
{
    public const RECONCILED = 'reconciled';

    public const DIFFERENCES = 'differences';

    public const BANK_UNREACHABLE = 'bank_unreachable';

    protected $table = 'ijp_daily_closes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['day' => 'date', 'differences' => 'array', 'bank_total' => 'integer', 'our_total' => 'integer'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
