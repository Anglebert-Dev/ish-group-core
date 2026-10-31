<?php

namespace Modules\Distribution\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Distribution\Enums\SiteStatus;
use Modules\Distribution\Enums\UpdateMode;

/**
 * A customer's self-hosted copy. Platform-level: no business_id.
 */
class Site extends BaseModel
{
    protected $table = 'distribution_sites';

    protected $fillable = [
        'name', 'customer_name', 'contact_email', 'app_url', 'update_mode', 'status', 'notes',
        'core_version', 'php_version', 'db_driver', 'modules_writable', 'last_check_in_at', 'last_ip', 'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'update_mode' => UpdateMode::class,
            'status' => SiteStatus::class,
            'modules_writable' => 'boolean',
            'last_check_in_at' => 'datetime',
            'installed_at' => 'datetime',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(SiteModule::class)->orderBy('module');
    }

    public function builds(): HasMany
    {
        return $this->hasMany(Build::class)->latest('id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class)->latest('id');
    }

    public function updateReports(): HasMany
    {
        return $this->hasMany(UpdateReport::class)->latest('id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(SiteToken::class)->latest('id');
    }

    /**
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->modules()->pluck('module')->all();
    }

    public function isActive(): bool
    {
        return $this->status === SiteStatus::Active;
    }
}
