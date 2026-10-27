<?php

namespace App\Libraries;

use Illuminate\Support\Str;

class UuidLibrary
{
    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    public static function isValid(string $value): bool
    {
        return Str::isUuid($value);
    }
}
