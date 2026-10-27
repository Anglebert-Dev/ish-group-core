<?php

namespace App\Enums;

enum DriverEmploymentType: string
{
    case Employed = 'employed';
    case Contractor = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::Employed => 'Employed',
            self::Contractor => 'Contractor',
        };
    }
}
