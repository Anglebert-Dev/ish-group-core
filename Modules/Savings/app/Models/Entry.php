<?php

namespace Modules\Savings\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    use BelongsToBusiness;

    protected $table = 'sav_entries';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'effective_date' => 'date'];
    }

    public $timestamps = false;

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
