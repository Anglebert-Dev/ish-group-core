<?php

namespace App\Models\Role;

use App\Models\Business\Business;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Tenant-scoped role. Stored name is "<Name>#<business_id>" so spatie's
 * unique(name, guard_name) holds across businesses; display_name strips it.
 */
class Role extends SpatieRole
{
    use \App\Models\Concerns\LogsActivity;

    public const SEPARATOR = '#';

    public const ADMIN = 'Admin';

    /**
     * {role} route parameters only resolve within the current business.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where('guard_name', 'staff')
            ->where('business_id', app(CurrentBusiness::class)->id());
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function scopedName(string $name, int $businessId): string
    {
        return $name.self::SEPARATOR.$businessId;
    }

    public static function displayName(string $storedName): string
    {
        return str_contains($storedName, self::SEPARATOR)
            ? strstr($storedName, self::SEPARATOR, true)
            : $storedName;
    }

    public function getDisplayNameAttribute(): string
    {
        return self::displayName($this->name);
    }

    public function isAdmin(): bool
    {
        return $this->business_id !== null
            && $this->name === self::scopedName(self::ADMIN, (int) $this->business_id);
    }
}
