<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case ReadyForPickup = 'ready_for_pickup';
    case ActiveRental = 'active_rental';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::ReadyForPickup => 'Ready for Pickup',
            self::ActiveRental => 'Active Rental',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }
}
