<?php

namespace App\Enums;

enum InspectionType: string
{
    case Pickup = 'pickup';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pickup',
            self::Return => 'Return',
        };
    }
}
