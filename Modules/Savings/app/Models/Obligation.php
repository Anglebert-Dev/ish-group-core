<?php

namespace Modules\Savings\Models;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Savings\Support\BelongsToOwner;

class Obligation extends BaseModel
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_obligations';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ObligationItem::class)->orderBy('effective_date')->orderBy('id');
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Line::class);
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
