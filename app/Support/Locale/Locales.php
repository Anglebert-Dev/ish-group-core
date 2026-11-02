<?php

namespace App\Support\Locale;

use App\Models\Business\Business;
use App\Models\User;
use App\Support\Settings\BusinessSettings;

/**
 * Which languages the app offers and which one a request uses.
 * A language is offered once resources/lang/{code} exists.
 *
 * Order: the user's own choice → their business's default → the app default.
 * Guests: what they picked this session → the platform's default.
 */
final class Locales
{
    public const SESSION = 'locale';

    /** @return array<string, string> code => name */
    public static function available(): array
    {
        $names = (array) config('app.locales', []);
        $codes = array_map('basename', glob(resource_path('lang/*'), GLOB_ONLYDIR) ?: []);

        $available = [];
        foreach ($codes as $code) {
            if (preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $code)) {
                $available[$code] = $names[$code] ?? strtoupper($code);
            }
        }

        return $available ?: [config('app.fallback_locale', 'en') => $names['en'] ?? 'English'];
    }

    public static function isAvailable(?string $code): bool
    {
        return is_string($code) && array_key_exists($code, self::available());
    }

    public static function businessDefault(?Business $business): ?string
    {
        if (! $business?->exists) {
            return null;
        }
        $code = app(BusinessSettings::class)->get('branding.locale', null, $business);

        return self::isAvailable($code) ? $code : null;
    }

    public static function resolve(?User $user, ?Business $business, ?string $sessionChoice = null): string
    {
        foreach ([$user?->locale, $sessionChoice, self::businessDefault($business ?? $user?->business)] as $code) {
            if (self::isAvailable($code)) {
                return $code;
            }
        }

        return self::isAvailable(config('app.locale')) ? config('app.locale') : array_key_first(self::available());
    }
}
