<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;

class CashoutEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ijp_cashout_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
