<?php

namespace App\Models\Setting;

use App\Libraries\ColorLibrary;
use App\Models\BaseModel;

class Setting extends BaseModel
{
    public const DEFAULT_COMPANY_NAME = 'Car Rental OS';

    public const DEFAULT_BRAND_COLOR = '#124EB0';

    protected $fillable = [
        'company_name',
        'brand_color',
        'logo_path',
        'favicon_path',
        'status',
    ];

    public function companyName(): string
    {
        return filled($this->company_name) ? $this->company_name : self::DEFAULT_COMPANY_NAME;
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
            : '/favicon-32.png';
    }
}
