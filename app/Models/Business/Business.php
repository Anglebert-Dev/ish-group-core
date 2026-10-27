<?php

namespace App\Models\Business;

use App\Libraries\ColorLibrary;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant. Also carries the tenant's branding (name, colour, logo, favicon),
 * which every view receives as $branding.
 */
class Business extends BaseModel
{
    public const DEFAULT_NAME = 'ISH Group';

    public const DEFAULT_BRAND_COLOR = '#124EB0';

    protected $fillable = [
        'name',
        'owner_id',
        'is_active',
        'enabled_modules',
        'brand_color',
        'logo_path',
        'favicon_path',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enabled_modules' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Name of this business's locked admin role, e.g. "Admin#3".
     */
    public function adminRoleName(): string
    {
        return 'Admin#'.$this->id;
    }

    /**
     * Per-business feature toggle (reference doc §1c). A null list means
     * the business has not restricted anything yet, so everything is on.
     */
    public function hasModuleEnabled(string $module): bool
    {
        $enabled = $this->enabled_modules;

        return $enabled === null || in_array(strtolower($module), array_map('strtolower', $enabled), true);
    }

    public function companyName(): string
    {
        return filled($this->name) ? $this->name : self::DEFAULT_NAME;
    }

    public function brandColor(): string
    {
        $color = strtoupper((string) $this->brand_color);

        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : self::DEFAULT_BRAND_COLOR;
    }

    public function brandColorDark(): string
    {
        return ColorLibrary::darken($this->brandColor());
    }

    public function brandColorLight(): string
    {
        return ColorLibrary::lighten($this->brandColor());
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? '/storage/'.$this->logo_path : null;
    }

    public function faviconUrl(): string
    {
        return $this->favicon_path
            ? '/storage/'.$this->favicon_path
            : '/favicon.svg';
    }
}
