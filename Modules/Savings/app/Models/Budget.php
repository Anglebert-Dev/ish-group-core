<?php

namespace Modules\Savings\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Modules\Savings\Support\HasPublicId;

class Budget extends Model
{
    use BelongsToBusiness, HasPublicId;

    protected $table = 'sav_budgets';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'integer'];
    }

}
