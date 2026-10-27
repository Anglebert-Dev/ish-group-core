<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide key/value store. Holds "<module>_version" rows (a module is
 * only "installed" when its row exists) and platform settings such as
 * allow_registration.
 */
class System extends Model
{
    protected $table = 'system';

    protected $fillable = ['key', 'value'];

    public static function getProperty(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('system')) {
            return $default;
        }

        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string|null>
     */
    public static function getProperties(array $keys): array
    {
        return static::query()->whereIn('key', $keys)->pluck('value', 'key')->all();
    }

    public static function addProperty(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function setProperty(string $key, mixed $value): void
    {
        static::query()->where('key', $key)->update(['value' => $value]);
    }

    public static function removeProperty(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}
