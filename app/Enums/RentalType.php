<?php

namespace App\Enums;

enum RentalType: string
{
    case SelfDrive = 'self_drive';
    case Chauffeur = 'chauffeur';

    public function label(): string
    {
        return match ($this) {
            self::SelfDrive => 'Self-Drive',
            self::Chauffeur => 'Chauffeur',
        };
    }
}
