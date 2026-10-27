<?php

namespace App\Enums;

enum FuelLevel: string
{
    case Empty = 'empty';
    case Quarter = 'quarter';
    case Half = 'half';
    case ThreeQuarter = 'three_quarter';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Empty',
            self::Quarter => '1/4',
            self::Half => '1/2',
            self::ThreeQuarter => '3/4',
            self::Full => 'Full',
        };
    }
}
