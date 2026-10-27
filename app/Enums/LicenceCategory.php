<?php

namespace App\Enums;

enum LicenceCategory: string
{
    case B = 'B';
    case B1 = 'B1';
    case C = 'C';
    case D = 'D';
    case D1 = 'D1';
    case E = 'E';
    case F = 'F';

    public function label(): string
    {
        return $this->value;
    }
}
