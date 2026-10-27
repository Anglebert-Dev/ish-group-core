<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case OnHire = 'on_hire';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::OnHire => 'On Hire',
            self::Maintenance => 'Maintenance',
            self::Inactive => 'Inactive',
        };
    }
}
