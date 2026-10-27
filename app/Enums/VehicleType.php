<?php

namespace App\Enums;

enum VehicleType: string
{
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Crossover = 'crossover';
    case Hatchback = 'hatchback';
    case Wagon = 'wagon';
    case Convertible = 'convertible';
    case Coupe = 'coupe';
    case Van = 'van';
    case Minivan = 'minivan';
    case Pickup = 'pickup';
    case Truck = 'truck';
    case Bus = 'bus';
    case Motorcycle = 'motorcycle';

    public function label(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::Suv => 'SUV',
            self::Crossover => 'Crossover (CUV)',
            self::Hatchback => 'Hatchback',
            self::Wagon => 'Station Wagon',
            self::Convertible => 'Convertible',
            self::Coupe => 'Coupe',
            self::Van => 'Van',
            self::Minivan => 'Minivan',
            self::Pickup => 'Pickup Truck',
            self::Truck => 'Commercial Truck',
            self::Bus => 'Bus',
            self::Motorcycle => 'Motorcycle',
        };
    }
}
