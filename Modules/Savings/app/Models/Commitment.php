<?php

namespace Modules\Savings\Models;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Savings\Support\BelongsToOwner;

class Commitment extends BaseModel
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_commitments';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'due_date' => 'date'];
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
