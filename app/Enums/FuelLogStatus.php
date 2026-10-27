<?php

namespace App\Enums;

enum FuelLogStatus: string
{
    case Recorded = 'recorded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Voided => 'Voided',
        };
    }
}
