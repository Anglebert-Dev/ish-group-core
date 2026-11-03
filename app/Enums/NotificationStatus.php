<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Unread = 'unread';
    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::Unread => __('body.notification_status_unread'),
            self::Read => __('body.notification_status_read'),
        };
    }
}
