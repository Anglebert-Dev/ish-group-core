<?php

namespace Modules\Savings\Models;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Savings\Support\BelongsToOwner;

class Account extends BaseModel
{
    use BelongsToBusiness, BelongsToOwner;

    protected $table = 'sav_accounts';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['opening_date' => 'date', 'opening_balance' => 'integer', 'minimum_balance' => 'integer', 'is_liquid' => 'boolean'];
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isLiability(): bool
    {
        return in_array($this->type, ['credit_card', 'loan'], true);
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
