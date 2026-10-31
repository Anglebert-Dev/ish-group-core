<?php

namespace Modules\Distribution\Models;

use Illuminate\Database\Eloquent\Model;

/** One check-in from a site: what it reported, what we offered. */
class CheckIn extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'distribution_check_ins';

    protected $fillable = ['site_id', 'ip', 'core_version', 'reported', 'offered'];

    protected function casts(): array
    {
        return ['reported' => 'array', 'offered' => 'array'];
    }
}
