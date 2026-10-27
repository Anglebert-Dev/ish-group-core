<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Service = 'service';
    case Repair = 'repair';
    case OilChange = 'oil_change';
    case Tyres = 'tyres';
    case Inspection = 'inspection';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Repair => 'Repair',
            self::OilChange => 'Oil Change',
            self::Tyres => 'Tyres',
            self::Inspection => 'Inspection',
        };
    }
}
