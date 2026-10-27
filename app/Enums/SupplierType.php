<?php

namespace App\Enums;

enum SupplierType: string
{
    case Parts = 'parts';
    case Fuel = 'fuel';
    case Insurance = 'insurance';
    case Services = 'services';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Parts => 'Parts',
            self::Fuel => 'Fuel',
            self::Insurance => 'Insurance',
            self::Services => 'Services',
            self::Other => 'Other',
        };
    }
}
