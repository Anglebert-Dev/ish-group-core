<?php

namespace Modules\Distribution\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Distribution\Enums\SiteModuleStatus;

class SiteModule extends Model
{
    protected $table = 'distribution_site_modules';

    protected $fillable = ['site_id', 'module', 'installed_version', 'status', 'last_error', 'last_reported_at'];

    protected function casts(): array
    {
        return [
            'status' => SiteModuleStatus::class,
            'last_reported_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
