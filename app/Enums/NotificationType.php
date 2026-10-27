<?php

namespace App\Enums;

enum NotificationType: string
{
    case System = 'system';
    case Booking = 'booking';
    case Payment = 'payment';
    case Finance = 'finance';
    case Fleet = 'fleet';

    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Booking => 'Booking',
            self::Payment => 'Payment',
            self::Finance => 'Finance',
            self::Fleet => 'Fleet',
        };
    }
}
