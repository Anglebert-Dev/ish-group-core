<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('body.user_status_pending'),
            self::Active => __('body.user_status_active'),
            self::Banned => __('body.user_status_banned'),
        };
    }
}
