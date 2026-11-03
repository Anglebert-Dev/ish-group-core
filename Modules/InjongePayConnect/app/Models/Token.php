<?php

namespace Modules\InjongePayConnect\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ijpc_tokens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
