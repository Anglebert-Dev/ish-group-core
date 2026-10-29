<?php

namespace Modules\Savings\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    use BelongsToBusiness;

    protected $table = 'sav_lines';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'effective_date' => 'date'];
    }

    public $timestamps = false;

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
