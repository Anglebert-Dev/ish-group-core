<?php

namespace Modules\Savings\Support;

use App\Support\Settings\BusinessSettings;

/**
 * Finance settings per user ("savings.user.<id>.<key>"), falling back to the
 * business default ("savings.<key>") and then to config.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = app(BusinessSettings::class);
        $owner = app(CurrentOwner::class)->id();

        $value = $owner ? $settings->get("savings.user.{$owner}.{$key}") : null;

        return $value ?? $settings->get("savings.{$key}", $default);
    }

    public static function set(string $key, mixed $value): void
    {
        $owner = app(CurrentOwner::class)->id();

        app(BusinessSettings::class)->set($owner ? "savings.user.{$owner}.{$key}" : "savings.{$key}", $value);
    }
}
