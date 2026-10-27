<?php

namespace App\Enums;

enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Petrol',
            self::Diesel => 'Diesel',
            self::Electric => 'Electric (EV)',
            self::Hybrid => 'Hybrid',
        };
    }
}
