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
            self::System => 'System',
            self::Info => 'Info',
            self::Success => 'Success',
            self::Warning => 'Warning',
            self::Security => 'Security',
        };
    }
}
