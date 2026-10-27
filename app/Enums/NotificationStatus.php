<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Unread = 'unread';
    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::Unread => 'Unread',
            self::Read => 'Read',
        };
    }
}
