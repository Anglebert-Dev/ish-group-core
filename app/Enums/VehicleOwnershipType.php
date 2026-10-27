<?php

namespace App\Enums;

enum VehicleOwnershipType: string
{
    case Company = 'company';
    case Outsider = 'outsider';

    public function label(): string
    {
        return match ($this) {
            self::Company => __('body.company_fleet'),
            self::Outsider => __('body.outsider_owner'),
        };
    }
}
