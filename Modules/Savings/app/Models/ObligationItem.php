<?php

namespace Modules\Savings\Models;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Modules\Savings\Support\BelongsToOwner;

class ObligationItem extends Model
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_obligation_items';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'effective_date' => 'date'];
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
