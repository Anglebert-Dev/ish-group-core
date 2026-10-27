<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Live make / model data from NHTSA vPIC (free, no API key).
 *
 * @see https://vpic.nhtsa.dot.gov/api/
 */
class VehicleCatalogApiLibrary
{
    private const CACHE_MAKES = 'vehicle_catalog.nhtsa.makes';

    private const CACHE_MODELS = 'vehicle_catalog.nhtsa.models.';

    /**
     * Passenger cars, light trucks / SUVs for rental fleets.
     *
     * @var list<string>
     */
    private const VEHICLE_TYPES = [
        'car',
        'truck',
        'multipurpose passenger vehicle (mpv)',
    ];

    /**
     * @var array<string, string>
     */
    private const MAKE_ALIASES = [
        'BMW' => 'BMW',
        'GMC' => 'GMC',
        'MG' => 'MG',
        'BYD' => 'BYD',
        'MINI' => 'MINI',
        'VW' => 'Volkswagen',
        'VOLKSWAGEN' => 'Volkswagen',
        'MERCEDES-BENZ' => 'Mercedes-Benz',
        'MERCEDES BENZ' => 'Mercedes-Benz',
        'LAND ROVER' => 'Land Rover',
        'ROLLS-ROYCE' => 'Rolls-Royce',
        'ASTON MARTIN' => 'Aston Martin',
        'ALFA ROMEO' => 'Alfa Romeo',
        'CITROEN' => 'Citroën',
        'CITROËN' => 'Citroën',
        'SKODA' => 'Škoda',
        'ŠKODA' => 'Škoda',
        'SEAT' => 'SEAT',
        'DS' => 'DS',
        'RAM' => 'Ram',
        'FIAT' => 'Fiat',
        'KIA' => 'Kia',
    ];

    public static function enabled(): bool
    {
        try {
            return (bool) config('services.nhtsa.enabled', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public static function makes(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return Cache::remember(self::CACHE_MAKES, now()->addDays(7), function () {
            $base = rtrim((string) config('services.nhtsa.base_url', 'https://vpic.nhtsa.dot.gov/api'), '/');
            $timeout = (int) config('services.nhtsa.timeout', 20);
            $names = [];

            try {
                $responses = Http::pool(fn ($pool) => collect(self::VEHICLE_TYPES)->map(
                    fn (string $type) => $pool
                        ->as($type)
                        ->timeout($timeout)
                        ->acceptJson()
                        ->get($base.'/vehicles/GetMakesForVehicleType/'.rawurlencode($type), [
                            'format' => 'json',
                        ])
                )->all());
            } catch (\Throwable $e) {
                Log::warning('NHTSA vehicle catalog makes pool failed.', [
                    'message' => $e->getMessage(),
                ]);

                return [];
            }

            foreach (self::VEHICLE_TYPES as $type) {
                $response = $responses[$type] ?? null;
                if (! $response || ! $response->successful()) {
                    continue;
                }

                $json = $response->json();
                if (! is_array($json)) {
                    continue;
                }

                foreach ($json['Results'] ?? [] as $row) {
                    $name = trim((string) ($row['MakeName'] ?? $row['Make_Name'] ?? ''));
                    $display = self::normalizeMake($name);
                    if ($display !== '') {
                        $names[$display] = true;
                    }
                }
            }

            $makes = array_keys($names);
            natcasesort($makes);

            return array_values($makes);
        });
    }

    /**
     * Cached models only — never hits the network.
     *
     * @return list<string>
     */
    public static function cachedModelsFor(string $make): array
    {
        if ($make === '') {
            return [];
        }

        try {
            $cached = Cache::get(self::CACHE_MODELS.Str::slug($make));
        } catch (\Throwable) {
            return [];
        }

        return is_array($cached) ? array_values($cached) : [];
    }

    /**
     * @return list<string>
     */
    public static function modelsFor(string $make): array
    {
        if (! self::enabled() || $make === '') {
            return [];
        }

        $cacheKey = self::CACHE_MODELS.Str::slug($make);

        return Cache::remember($cacheKey, now()->addDays(3), function () use ($make) {
            $response = self::get('/vehicles/GetModelsForMake/'.rawurlencode($make), [
                'format' => 'json',
            ]);

            if ($response === null) {
                return [];
            }

            $models = [];
            foreach ($response['Results'] ?? [] as $row) {
                $name = trim((string) ($row['Model_Name'] ?? ''));
                if ($name !== '') {
                    $models[$name] = true;
                }
            }

            $list = array_keys($models);
            natcasesort($list);

            return array_values($list);
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected static function get(string $path, array $query = []): ?array
    {
        $base = rtrim((string) config('services.nhtsa.base_url', 'https://vpic.nhtsa.dot.gov/api'), '/');
        $timeout = (int) config('services.nhtsa.timeout', 20);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($base.$path, $query);

            if (! $response->successful()) {
                Log::warning('NHTSA vehicle catalog request failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('NHTSA vehicle catalog request error.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function normalizeMake(string $make): string
    {
        $trimmed = trim($make);
        if ($trimmed === '') {
            return '';
        }

        $key = strtoupper(str_replace(['–', '—'], '-', $trimmed));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        if (isset(self::MAKE_ALIASES[$key])) {
            return self::MAKE_ALIASES[$key];
        }

        return Str::of($trimmed)->lower()->title()->toString();
    }

    public static function forgetCache(?string $make = null): void
    {
        Cache::forget(self::CACHE_MAKES);

        if ($make) {
            Cache::forget(self::CACHE_MODELS.Str::slug($make));
        }
    }
}
