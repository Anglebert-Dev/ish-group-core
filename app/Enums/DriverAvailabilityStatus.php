<?php

namespace App\Enums;

enum DriverAvailabilityStatus: string
{
    case Available = 'available';
    case OnJob = 'on_job';
    case OffDuty = 'off_duty';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::OnJob => 'On Job',
            self::OffDuty => 'Off Duty',
        };
    }
}
