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
        'name', 'customer_name', 'contact_email', 'app_url', 'update_mode', 'status', 'support_until', 'is_test', 'notes', 'server_details',
        'core_version', 'php_version', 'db_driver', 'modules_writable', 'last_check_in_at', 'last_ip', 'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'update_mode' => UpdateMode::class,
            'status' => SiteStatus::class,
            'modules_writable' => 'boolean',
            'is_test' => 'boolean',
            'server_details' => 'array',
            'last_check_in_at' => 'datetime',
            'installed_at' => 'datetime',
            'support_until' => 'date',
            'support_reminded_for' => 'date',
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

    public function canReceiveLinkKey(): bool
    {
        return $this->installed_at !== null || $this->builds()->where('status', 'ready')->where('standalone', false)->exists();
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class)->latest('id');
    }

    /**
     * Updates and support included right now? They start when the licence
     * (or a renewal) is paid: no date yet = none. Our own test sites always
     * have them. The customer's app keeps working either way; only updates stop.
     */
    public function hasSupport(): bool
    {
        return $this->is_test || ($this->support_until !== null && ! $this->support_until->isBefore(today()));
    }

    /** Has the customer paid for the licence? (Test sites: no licence needed.) */
    public function licencePaid(): bool
    {
        return $this->is_test || $this->charges()->where('kind', Charge::LICENCE)->where('status', 'paid')->exists();
    }

    /** Days until support ends (negative once ended); null = no end date. */
    public function supportDaysLeft(): ?int
    {
        return $this->support_until ? (int) today()->diffInDays($this->support_until, false) : null;
    }
}
