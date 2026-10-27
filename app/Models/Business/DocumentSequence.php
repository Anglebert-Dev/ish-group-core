<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter behind DocumentNumber. Never edited by hand.
 */
class DocumentSequence extends Model
{
    protected $fillable = ['business_id', 'type', 'year', 'last_number'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
