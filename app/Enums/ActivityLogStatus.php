<?php

namespace App\Enums;

enum ActivityLogStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('body.log_status_active'),
            self::Inactive => __('body.log_status_inactive'),
        };
    }
}
