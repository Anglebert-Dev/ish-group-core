<?php

namespace Modules\InjongePay\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionPin extends Model
{
    protected $table = 'ijp_transaction_pins';

    protected $guarded = ['id'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['locked_until' => 'datetime', 'set_at' => 'datetime'];
    }
}
