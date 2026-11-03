<?php

namespace Modules\InjongePayConnect\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCall extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ijpc_api_calls';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request' => 'array', 'response' => 'array', 'created_at' => 'datetime'];
    }
}
