<?php

namespace Modules\Distribution\Enums;

enum SiteStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';   // the update API refuses it

    public function label(): string
    {
        return __('distribution::distribution.status_'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'danger';
    }
}
