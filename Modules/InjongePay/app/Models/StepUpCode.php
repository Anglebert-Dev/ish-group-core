<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;

class StepUpCode extends Model
{
    protected $table = 'ijp_step_up_codes';

    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
