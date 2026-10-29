<?php

namespace Modules\Savings\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Expected extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'sav_expected';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expected_date' => 'date'];
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function toAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id')->withTrashed();
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'expected';
    }
}
