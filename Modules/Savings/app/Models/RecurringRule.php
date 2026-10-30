<?php

namespace Modules\Savings\Models;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Savings\Support\BelongsToOwner;

class RecurringRule extends BaseModel
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_recurring_rules';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'is_variable' => 'boolean', 'is_active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date', 'interval' => 'integer'];
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
