<?php

namespace App\Enums;

enum VehicleColor: string
{
    case White = 'White';
    case Black = 'Black';
    case Silver = 'Silver';
    case Grey = 'Grey';
    case Blue = 'Blue';
    case Red = 'Red';
    case Green = 'Green';
    case Brown = 'Brown';
    case Beige = 'Beige';
    case Gold = 'Gold';
    case Yellow = 'Yellow';
    case Orange = 'Orange';
    case Maroon = 'Maroon';
    case Navy = 'Navy';
    case Pearl = 'Pearl';
    case Champagne = 'Champagne';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function options(?string $current = null): array
    {
        $options = array_map(fn (self $color) => $color->value, self::cases());

        if (filled($current) && ! in_array($current, $options, true)) {
            $options[] = $current;
            natcasesort($options);
            $options = array_values($options);
        }

        return $options;
    }
}
