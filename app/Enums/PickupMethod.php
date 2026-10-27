<?php

namespace App\Enums;

enum PickupMethod: string
{
    case Station = 'station';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Station => __('body.pickup_method_station'),
            self::Delivery => __('body.pickup_method_delivery'),
        };
    }
}
