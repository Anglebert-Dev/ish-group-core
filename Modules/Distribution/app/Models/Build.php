<?php

namespace Modules\Distribution\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Distribution\Enums\BuildStatus;

/**
 * A zip made for one site: core + its modules + vendor, ready to extract.
 */
class Build extends BaseModel
{
    protected $table = 'distribution_builds';

    protected $fillable = [
        'site_id', 'token_id', 'core_version', 'modules', 'filename', 'path', 'sha256', 'size',
        'install_code_hash', 'status', 'error', 'expires_at',
    ];

    protected $hidden = ['install_code_hash'];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'size' => 'integer',
            'status' => BuildStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(SiteToken::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === BuildStatus::Ready && $this->expires_at?->isFuture();
    }
}
