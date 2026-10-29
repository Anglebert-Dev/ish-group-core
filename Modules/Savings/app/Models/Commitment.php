<?php

namespace Modules\Savings\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Commitment extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'sav_commitments';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'due_date' => 'date'];
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
