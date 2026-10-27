<?php

namespace App\Libraries;

/**
 * Showcase car brands for the public brand marquee.
 * Logo files live in public/assets/images/brands/ (Simple Icons, CC0).
 */
class BrandLibrary
{
    /**
     * Display name => local SVG filename (without .svg).
     *
     * @return array<string, string>
     */
    public static function logos(): array
    {
        return [
            'Toyota' => 'toyota',
            'Honda' => 'honda',
            'Suzuki' => 'suzuki',
            'Nissan' => 'nissan',
            'Hyundai' => 'hyundai',
            'Kia' => 'kia',
            'Mercedes-Benz' => 'mercedes',
            'BMW' => 'bmw',
            'Audi' => 'audi',
            'Volkswagen' => 'volkswagen',
            'Ford' => 'ford',
            'Mazda' => 'mazda',
            'Mitsubishi' => 'mitsubishi',
            'Land Rover' => 'landrover',
            'Jeep' => 'jeep',
            'Subaru' => 'subaru',
            'Peugeot' => 'peugeot',
            'Chevrolet' => 'chevrolet',
            'Volvo' => 'volvo',
            'Renault' => 'renault',
            'Porsche' => 'porsche',
            'Tesla' => 'tesla',
            'Fiat' => 'fiat',
            'Citroën' => 'citroen',
            'Škoda' => 'skoda',
            'SEAT' => 'seat',
            'Jaguar' => 'jaguar',
            'MINI' => 'mini',
            'Alfa Romeo' => 'alfaromeo',
            'Dacia' => 'dacia',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::logos());
    }

    public static function logoSlug(string $make): ?string
    {
        $logos = self::logos();
        if (isset($logos[$make])) {
            return $logos[$make];
        }

        $needle = \Illuminate\Support\Str::slug($make);

        foreach ($logos as $name => $slug) {
            if (\Illuminate\Support\Str::slug($name) === $needle || $slug === $needle) {
                return $slug;
            }
        }

        // Common aliases
        return match ($needle) {
            'mercedes', 'mercedes-benz' => 'mercedes',
            'vw', 'volkswagen' => 'volkswagen',
            'land-rover', 'landrover' => 'landrover',
            'alfa-romeo', 'alfaromeo' => 'alfaromeo',
            'citroen', 'citroën' => 'citroen',
            'skoda', 'škoda' => 'skoda',
            default => null,
        };
    }

    public static function logoUrl(string $make): ?string
    {
        $slug = self::logoSlug($make);
        if (! $slug) {
            return null;
        }

        $relative = 'assets/images/brands/'.$slug.'.svg';
        $absolute = public_path($relative);

        return is_file($absolute) ? asset($relative) : null;
    }
}
