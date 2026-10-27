<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';   // kept for history; cannot be used on new records
    case Banned = 'banned';       // website access blocked
    case Pending = 'pending';

    public function label(): string
    {
        return __('body.customer_status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'muted',
            self::Banned => 'danger',
            self::Pending => 'warning',
        };
    }
}
