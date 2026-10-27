<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

/**
 * One per-business setting. Read and write through BusinessSettings.
 */
class BusinessSetting extends Model
{
    protected $fillable = ['business_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
