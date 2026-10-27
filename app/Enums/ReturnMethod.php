<?php

namespace App\Enums;

enum ReturnMethod: string
{
    case Station = 'station';
    case Collection = 'collection';

    public function label(): string
    {
        return match ($this) {
            self::Station => __('body.return_method_station'),
            self::Collection => __('body.return_method_collection'),
        };
    }
}
