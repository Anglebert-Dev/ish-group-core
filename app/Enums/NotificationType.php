<?php

namespace App\Enums;

enum NotificationType: string
{
    case System = 'system';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::System => __('body.notification_type_system'),
            self::Info => __('body.notification_type_info'),
            self::Success => __('body.notification_type_success'),
            self::Warning => __('body.notification_type_warning'),
            self::Security => __('body.notification_type_security'),
        };
    }
}
