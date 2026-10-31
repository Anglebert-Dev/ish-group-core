<?php

namespace Modules\Distribution\Models;

use Illuminate\Database\Eloquent\Model;

/** The result of one install/update on a site, as the site reported it. */
class UpdateReport extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'distribution_update_reports';

    protected $fillable = ['site_id', 'module', 'action', 'from_version', 'to_version', 'status', 'error', 'duration_ms'];

    protected function casts(): array
    {
        return ['duration_ms' => 'integer'];
    }
}
