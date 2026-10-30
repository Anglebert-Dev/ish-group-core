<?php

namespace Modules\Savings\Models;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Savings\Support\BelongsToOwner;

class Goal extends BaseModel
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_goals';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['target_amount' => 'integer', 'monthly_contribution' => 'integer', 'target_date' => 'date'];
    }

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GoalAllocation::class)->orderBy('effective_date')->orderBy('id');
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
