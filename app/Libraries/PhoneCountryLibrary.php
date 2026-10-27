<?php

namespace App\Libraries;

class PhoneCountryLibrary
{
    /**
     * Curated dialing codes for the phone picker (ISO 3166-1 alpha-2).
     * `digits` is the max national significant number length (without country code).
     *
     * @return list<array{iso: string, name: string, dial: string, flag: string, digits: int}>
     */
    public static function countries(): array
    {
        return [
            ['iso' => 'RW', 'name' => 'Rwanda', 'dial' => '+250', 'flag' => '🇷🇼', 'digits' => 9],
            ['iso' => 'KE', 'name' => 'Kenya', 'dial' => '+254', 'flag' => '🇰🇪', 'digits' => 9],
            ['iso' => 'UG', 'name' => 'Uganda', 'dial' => '+256', 'flag' => '🇺🇬', 'digits' => 9],
            ['iso' => 'TZ', 'name' => 'Tanzania', 'dial' => '+255', 'flag' => '🇹🇿', 'digits' => 9],
            ['iso' => 'BI', 'name' => 'Burundi', 'dial' => '+257', 'flag' => '🇧🇮', 'digits' => 8],
            ['iso' => 'CD', 'name' => 'DR Congo', 'dial' => '+243', 'flag' => '🇨🇩', 'digits' => 9],
            ['iso' => 'CG', 'name' => 'Congo', 'dial' => '+242', 'flag' => '🇨🇬', 'digits' => 9],
            ['iso' => 'ET', 'name' => 'Ethiopia', 'dial' => '+251', 'flag' => '🇪🇹', 'digits' => 9],
            ['iso' => 'SS', 'name' => 'South Sudan', 'dial' => '+211', 'flag' => '🇸🇸', 'digits' => 9],
            ['iso' => 'SO', 'name' => 'Somalia', 'dial' => '+252', 'flag' => '🇸🇴', 'digits' => 9],
            ['iso' => 'DJ', 'name' => 'Djibouti', 'dial' => '+253', 'flag' => '🇩🇯', 'digits' => 8],
            ['iso' => 'ER', 'name' => 'Eritrea', 'dial' => '+291', 'flag' => '🇪🇷', 'digits' => 7],
            ['iso' => 'ZA', 'name' => 'South Africa', 'dial' => '+27', 'flag' => '🇿🇦', 'digits' => 9],
            ['iso' => 'NG', 'name' => 'Nigeria', 'dial' => '+234', 'flag' => '🇳🇬', 'digits' => 10],
            ['iso' => 'GH', 'name' => 'Ghana', 'dial' => '+233', 'flag' => '🇬🇭', 'digits' => 9],
            ['iso' => 'EG', 'name' => 'Egypt', 'dial' => '+20', 'flag' => '🇪🇬', 'digits' => 10],
            ['iso' => 'MA', 'name' => 'Morocco', 'dial' => '+212', 'flag' => '🇲🇦', 'digits' => 9],
            ['iso' => 'SN', 'name' => 'Senegal', 'dial' => '+221', 'flag' => '🇸🇳', 'digits' => 9],
            ['iso' => 'CI', 'name' => 'Côte d\'Ivoire', 'dial' => '+225', 'flag' => '🇨🇮', 'digits' => 10],
            ['iso' => 'CM', 'name' => 'Cameroon', 'dial' => '+237', 'flag' => '🇨🇲', 'digits' => 9],
            ['iso' => 'AE', 'name' => 'United Arab Emirates', 'dial' => '+971', 'flag' => '🇦🇪', 'digits' => 9],
            ['iso' => 'SA', 'name' => 'Saudi Arabia', 'dial' => '+966', 'flag' => '🇸🇦', 'digits' => 9],
            ['iso' => 'IN', 'name' => 'India', 'dial' => '+91', 'flag' => '🇮🇳', 'digits' => 10],
            ['iso' => 'CN', 'name' => 'China', 'dial' => '+86', 'flag' => '🇨🇳', 'digits' => 11],
            ['iso' => 'GB', 'name' => 'United Kingdom', 'dial' => '+44', 'flag' => '🇬🇧', 'digits' => 10],
            ['iso' => 'FR', 'name' => 'France', 'dial' => '+33', 'flag' => '🇫🇷', 'digits' => 9],
            ['iso' => 'DE', 'name' => 'Germany', 'dial' => '+49', 'flag' => '🇩🇪', 'digits' => 11],
            ['iso' => 'BE', 'name' => 'Belgium', 'dial' => '+32', 'flag' => '🇧🇪', 'digits' => 9],
            ['iso' => 'NL', 'name' => 'Netherlands', 'dial' => '+31', 'flag' => '🇳🇱', 'digits' => 9],
            ['iso' => 'US', 'name' => 'United States', 'dial' => '+1', 'flag' => '🇺🇸', 'digits' => 10],
            ['iso' => 'CA', 'name' => 'Canada', 'dial' => '+1', 'flag' => '🇨🇦', 'digits' => 10],
            ['iso' => 'AU', 'name' => 'Australia', 'dial' => '+61', 'flag' => '🇦🇺', 'digits' => 9],
            ['iso' => 'TR', 'name' => 'Türkiye', 'dial' => '+90', 'flag' => '🇹🇷', 'digits' => 10],
            ['iso' => 'GE', 'name' => 'Georgia', 'dial' => '+995', 'flag' => '🇬🇪', 'digits' => 9],
        ];
    }

    public static function defaultIso(): string
    {
        return 'RW';
    }

    /**
     * @return array{iso: string, name: string, dial: string, flag: string, digits: int}
     */
    public static function findByIso(string $iso): array
    {
        $iso = strtoupper($iso);

        foreach (self::countries() as $country) {
            if ($country['iso'] === $iso) {
                return $country;
            }
        }

        return self::findByIso(self::defaultIso());
    }

    /**
     * Split a stored E.164-ish phone into country + national number.
     *
     * @return array{iso: string, dial: string, national: string, digits: int}
     */
    public static function split(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $default = self::findByIso(self::defaultIso());

        if ($digits === '') {
            return [
                'iso' => $default['iso'],
                'dial' => $default['dial'],
                'national' => '',
                'digits' => $default['digits'],
            ];
        }

        $withPlus = '+'.$digits;
        $sorted = self::countries();
        usort($sorted, fn ($a, $b) => strlen($b['dial']) <=> strlen($a['dial']));

        foreach ($sorted as $country) {
            if (str_starts_with($withPlus, $country['dial'])) {
                $national = substr($digits, strlen($country['dial']) - 1);
                $national = substr(preg_replace('/\D+/', '', $national) ?? '', 0, $country['digits']);

                return [
                    'iso' => $country['iso'],
                    'dial' => $country['dial'],
                    'national' => $national,
                    'digits' => $country['digits'],
                ];
            }
        }

        $national = substr($digits, 0, $default['digits']);

        return [
            'iso' => $default['iso'],
            'dial' => $default['dial'],
            'national' => $national,
            'digits' => $default['digits'],
        ];
    }
}
