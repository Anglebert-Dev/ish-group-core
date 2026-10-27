<?php

namespace App\Enums;

enum ExtraType: string
{
    case Optional = 'optional';
    case Chauffeur = 'chauffeur';
    case Delivery = 'delivery';
    case Collection = 'collection';
    case Extension = 'extension';

    public function label(): string
    {
        return match ($this) {
            self::Optional => 'Optional add-on',
            self::Chauffeur => 'Chauffeur service',
            self::Delivery => 'Vehicle delivery',
            self::Collection => 'Vehicle collection',
            self::Extension => 'Extension surcharge',
        };
    }
}
