<?php

namespace App\Libraries;

use App\Models\Vehicle\Vehicle;

/**
 * Shared make / model catalog for the fleet vehicle form.
 * Makes stay aligned with BrandLibrary where logos exist.
 */
class VehicleCatalogLibrary
{
    /**
     * Static fallback catalog (used when NHTSA is offline / disabled).
     *
     * @return array<string, list<string>>
     */
    public static function catalog(): array
    {
        return [
            'Toyota' => [
                'Corolla',
                'Camry',
                'Yaris',
                'RAV4',
                'bZ4X',
                'Land Cruiser',
                'Land Cruiser Prado',
                'Fortuner',
                'Hilux',
                'Hiace',
                'Wish',
                'Noah',
                'Voxy',
                'Harrier',
            ],
            'Honda' => [
                'Civic',
                'Accord',
                'Fit',
                'CR-V',
                'HR-V',
                'e:Ny1',
                'Prologue',
                'Pilot',
            ],
            'Suzuki' => [
                'Swift',
                'Dzire',
                'Alto',
                'Vitara',
                'Jimny',
                'Ertiga',
            ],
            'Nissan' => [
                'Note',
                'Sunny',
                'Leaf',
                'Ariya',
                'X-Trail',
                'Qashqai',
                'Patrol',
                'Navara',
                'Serena',
            ],
            'Hyundai' => [
                'i10',
                'i20',
                'Accent',
                'Elantra',
                'Tucson',
                'Santa Fe',
                'Creta',
                'Kona Electric',
                'Ioniq 5',
                'Ioniq 6',
            ],
            'Kia' => [
                'Picanto',
                'Rio',
                'Cerato',
                'Sportage',
                'Sorento',
                'Seltos',
                'Niro EV',
                'EV6',
                'EV9',
            ],
            'Mercedes-Benz' => [
                'A-Class',
                'C-Class',
                'E-Class',
                'S-Class',
                'GLA',
                'GLC',
                'GLE',
                'G-Class',
                'EQA',
                'EQB',
                'EQC',
                'EQE',
                'EQS',
                'EQS SUV',
                'Vito',
                'Sprinter',
                'eVito',
                'eSprinter',
            ],
            'BMW' => [
                '1 Series',
                '2 Series',
                '3 Series',
                '5 Series',
                'X1',
                'X3',
                'X5',
                'X6',
                'i4',
                'i5',
                'i7',
                'iX1',
                'iX3',
                'iX',
            ],
            'Audi' => [
                'A3',
                'A4',
                'A6',
                'Q3',
                'Q5',
                'Q7',
                'Q4 e-tron',
                'Q6 e-tron',
                'Q8 e-tron',
                'e-tron GT',
            ],
            'Volkswagen' => [
                'Polo',
                'Golf',
                'Passat',
                'Tiguan',
                'Touareg',
                'Amarok',
                'ID.3',
                'ID.4',
                'ID.5',
                'ID.7',
                'ID.Buzz',
            ],
            'Ford' => [
                'Fiesta',
                'Focus',
                'Ranger',
                'Everest',
                'EcoSport',
                'Territory',
                'Mustang Mach-E',
                'F-150 Lightning',
                'E-Transit',
            ],
            'Mazda' => [
                'Mazda2',
                'Mazda3',
                'Mazda6',
                'CX-3',
                'CX-5',
                'CX-9',
                'BT-50',
                'MX-30',
            ],
            'Mitsubishi' => [
                'Mirage',
                'Lancer',
                'ASX',
                'Outlander',
                'Pajero',
                'L200',
            ],
            'Land Rover' => [
                'Defender',
                'Discovery',
                'Discovery Sport',
                'Range Rover',
                'Range Rover Sport',
                'Range Rover Evoque',
            ],
            'Jeep' => [
                'Renegade',
                'Compass',
                'Cherokee',
                'Grand Cherokee',
                'Wrangler',
                'Avenger Electric',
            ],
            'Subaru' => [
                'Impreza',
                'Legacy',
                'Forester',
                'Outback',
                'XV',
                'Solterra',
            ],
            'Peugeot' => [
                '208',
                '301',
                '308',
                '3008',
                '5008',
                'Partner',
                'e-208',
                'e-2008',
                'e-308',
                'E-3008',
            ],
            'Chevrolet' => [
                'Spark',
                'Aveo',
                'Cruze',
                'Captiva',
                'Trailblazer',
                'Bolt EV',
                'Bolt EUV',
                'Equinox EV',
            ],
            'Volvo' => [
                'S60',
                'S90',
                'XC40',
                'XC60',
                'XC90',
                'EX30',
                'EX40',
                'EC40',
                'EX90',
            ],
            'Renault' => [
                'Clio',
                'Megane',
                'Duster',
                'Captur',
                'Kadjar',
                'Zoe',
                'Megane E-Tech',
                'Scenic E-Tech',
                'Kangoo E-Tech',
            ],
            'Porsche' => [
                'Macan',
                'Cayenne',
                'Panamera',
                'Taycan',
                'Macan Electric',
            ],
            'Tesla' => [
                'Model 3',
                'Model Y',
                'Model S',
                'Model X',
                'Cybertruck',
            ],
            'BYD' => [
                'Atto 3',
                'Dolphin',
                'Seal',
                'Sealion 6',
                'Sealion 7',
                'Tang',
                'Han',
                'Song Plus',
            ],
            'MG' => [
                'MG4',
                'ZS EV',
                'MG5 EV',
                'Marvel R',
                'Cyberster',
            ],
            'Polestar' => [
                '2',
                '3',
                '4',
            ],
            'Fiat' => [
                '500',
                '500e',
                'Panda',
                'Tipo',
                'Doblo',
                'E-Doblo',
            ],
            'Citroën' => [
                'C3',
                'C4',
                'C5 Aircross',
                'Berlingo',
                'ë-C3',
                'ë-C4',
                'ë-Berlingo',
            ],
            'Škoda' => [
                'Fabia',
                'Octavia',
                'Superb',
                'Kodiaq',
                'Karoq',
                'Enyaq',
                'Elroq',
            ],
            'SEAT' => [
                'Ibiza',
                'Leon',
                'Arona',
                'Ateca',
            ],
            'Cupra' => [
                'Born',
                'Tavascan',
            ],
            'Jaguar' => [
                'XE',
                'XF',
                'F-Pace',
                'E-Pace',
                'I-Pace',
            ],
            'MINI' => [
                'Cooper',
                'Cooper SE',
                'Countryman',
                'Countryman SE',
                'Clubman',
                'Aceman',
            ],
            'Alfa Romeo' => [
                'Giulia',
                'Stelvio',
                'Tonale',
                'Junior Elettrica',
            ],
            'Dacia' => [
                'Sandero',
                'Logan',
                'Duster',
                'Jogger',
                'Spring',
            ],
            'Isuzu' => [
                'D-Max',
                'mu-X',
            ],
            'VinFast' => [
                'VF 5',
                'VF 6',
                'VF 7',
                'VF 8',
                'VF 9',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function makes(): array
    {
        $makes = array_keys(self::catalog());

        foreach (VehicleCatalogApiLibrary::makes() as $make) {
            if (! in_array($make, $makes, true)) {
                $makes[] = $make;
            }
        }

        natcasesort($makes);

        return array_values($makes);
    }

    /**
     * @return list<string>
     */
    public static function modelsFor(?string $make): array
    {
        if (! filled($make)) {
            return [];
        }

        $models = self::catalog()[$make] ?? [];

        // Match API make casing / aliases against static keys.
        if ($models === []) {
            foreach (self::catalog() as $catalogMake => $catalogModels) {
                if (strcasecmp($catalogMake, $make) === 0) {
                    $models = $catalogModels;
                    break;
                }
            }
        }

        // Prefer a warm cache so the fleet form never waits on NHTSA when we
        // already have a local catalog. Only call the live API as a fallback.
        $apiModels = VehicleCatalogApiLibrary::cachedModelsFor($make);
        if ($apiModels === [] && $models === []) {
            $apiModels = VehicleCatalogApiLibrary::modelsFor($make);
        }

        foreach ($apiModels as $model) {
            if (! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        natcasesort($models);

        return array_values($models);
    }

    /**
     * @return list<int>
     */
    public static function years(): array
    {
        $max = (int) date('Y') + 1;
        $min = 1980;

        return range($max, $min);
    }

    /**
     * Rental category name for a make / model (matches seeded category names).
     */
    public static function rentalCategoryFor(?string $make, ?string $model): ?string
    {
        $model = trim((string) $model);
        if ($model === '') {
            return null;
        }

        $overrideKey = strtolower(trim((string) $make)).'|'.strtolower($model);
        $overrides = self::rentalCategoryOverrides();
        if (isset($overrides[$overrideKey])) {
            return $overrides[$overrideKey];
        }

        return self::inferRentalCategory($model);
    }

    /**
     * @param  list<string>|null  $models
     * @return array<string, string>
     */
    public static function rentalCategoriesFor(?string $make, ?array $models = null): array
    {
        $models ??= self::modelsFor($make);
        $map = [];

        foreach ($models as $model) {
            $category = self::rentalCategoryFor($make, (string) $model);
            if ($category !== null) {
                $map[(string) $model] = $category;
            }
        }

        return $map;
    }

    /**
     * Suggested specification values for the fleet form (vehicle type, seats, etc.).
     *
     * @return array{
     *     vehicle_type: string,
     *     seats: int,
     *     transmission: string,
     *     fuel_type: string,
     *     required_license_category: string|null
     * }|null
     */
    public static function suggestedSpecsFor(?string $make, ?string $model): ?array
    {
        $model = trim((string) $model);
        if ($model === '') {
            return null;
        }

        $category = self::rentalCategoryFor($make, $model) ?? 'Sedan';
        $fuel = self::inferFuelType($model);
        $transmission = self::inferTransmission($model, $category);
        $vehicleType = self::inferVehicleType($model, $category);
        $seats = self::inferSeats($model, $category, $vehicleType);

        return [
            'vehicle_type' => $vehicleType,
            'seats' => $seats,
            'transmission' => $transmission,
            'fuel_type' => $fuel,
            'required_license_category' => $seats >= 9 ? 'D1' : 'B',
        ];
    }

    /**
     * @param  list<string>|null  $models
     * @return array<string, array{
     *     vehicle_type: string,
     *     seats: int,
     *     transmission: string,
     *     fuel_type: string,
     *     required_license_category: string|null
     * }>
     */
    public static function specsFor(?string $make, ?array $models = null): array
    {
        $models ??= self::modelsFor($make);
        $map = [];

        foreach ($models as $modelName) {
            $specs = self::suggestedSpecsFor($make, (string) $modelName);
            if ($specs !== null) {
                $map[(string) $modelName] = $specs;
            }
        }

        return $map;
    }

    private static function inferVehicleType(string $model, string $category): string
    {
        if ($category === 'Pickup') {
            return 'pickup';
        }
        if ($category === 'Van') {
            return self::modelMatches($model, ['sienna', 'odyssey', 'carnival', 'previa', 'wish'])
                ? 'minivan'
                : 'van';
        }
        if ($category === 'Economy') {
            return 'hatchback';
        }
        if ($category === 'SUV') {
            return self::modelMatches($model, [
                'cr-v', 'crv', 'hr-v', 'hrv', 'cx-3', 'cx-5', 'cx5', 'qashqai',
                'x-trail', 'tucson', 'sportage', 'seltos', 'captur', 'kona', 'creta',
                'rav4', 'asx', 'ecosport', 'taos', 't-roc', 't-cross', 'crossover',
            ]) ? 'crossover' : 'suv';
        }

        return 'sedan';
    }

    private static function inferFuelType(string $model): string
    {
        if (self::modelMatches($model, [
            'leaf', 'zoe', 'bolt', 'model 3', 'model y', 'model s', 'model x',
            'ioniq 5', 'ioniq 6', 'ev6', 'ev9', 'niro ev', 'id.3', 'id.4', 'id.5',
            'id.7', 'id.buzz', 'bz4x', 'atto 3', 'dolphin', 'mg4', 'zs ev',
            'mg5 ev', 'e-tron', 'eqa', 'eqb', 'eqc', 'eqe', 'eqs', 'i4', 'i5',
            'i7', 'ix', 'ix1', 'ix3', 'mach-e', 'ariya', 'solterra', 'enyaq',
            'born', 'tavascan', '500e', 'e-208', 'e-2008', 'e-308', 'ë-c3',
            'ë-c4', 'spring', 'cybertruck', 'prologue', 'vf 5', 'vf 6', 'vf 7',
            'vf 8', 'vf 9', 'polestar', 'ex30', 'ex40', 'ec40', 'ex90',
        ])) {
            return 'electric';
        }

        if (self::modelMatches($model, [
            'hybrid', 'prius', 'camry', 'rav4 hybrid', 'nx', 'rx', 'ioniq',
            'sealion', 'song plus',
        ]) || str_contains(strtolower($model), 'hybrid')
            || str_contains(strtolower($model), 'e-tech')
            || str_contains(strtolower($model), 'phev')) {
            return 'hybrid';
        }

        if (self::modelMatches($model, [
            'hilux', 'fortuner', 'land cruiser', 'prado', 'hiace', 'navara',
            'ranger', 'amarok', 'd-max', 'dmax', 'l200', 'bt-50', 'sprinter',
            'vito', 'transit', 'patrol', 'everest', 'mu-x',
        ])) {
            return 'diesel';
        }

        return 'petrol';
    }

    private static function inferTransmission(string $model, string $category): string
    {
        if (self::modelMatches($model, [
            'swift', 'alto', 'picanto', 'i10', 'hiace', 'sandero', 'logan',
            'dzire', 'mirage', 'spark',
        ])) {
            return 'manual';
        }

        if ($category === 'Economy' && self::modelMatches($model, ['polo', 'fiesta', 'rio', 'i20'])) {
            return 'manual';
        }

        return 'automatic';
    }

    private static function inferSeats(string $model, string $category, string $vehicleType): int
    {
        if (self::modelMatches($model, ['hiace', 'urvan', 'nv350', 'sprinter', 'transit', 'crafter'])) {
            return 14;
        }

        if ($vehicleType === 'minivan' || self::modelMatches($model, [
            'carnival', 'sienna', 'odyssey', 'alphard', 'voxy', 'noah', 'serena',
            'staria', 'wish', 'previa', 'id.buzz',
        ])) {
            return 7;
        }

        if (self::modelMatches($model, [
            'prado', 'land cruiser', 'fortuner', 'patrol', 'pajero', 'xc90',
            'gls', 'q7', 'q8', 'sorento', 'santa fe', 'palisade', 'telluride',
            'pilot', 'pathfinder', 'highlander', 'discovery', 'ev9', 'vf 9',
            '5008', 'kodiaq',
        ])) {
            return 7;
        }

        if ($category === 'Van') {
            return 8;
        }

        return 5;
    }

    /**
     * Exact make|model overrides when name inference would be wrong.
     *
     * @return array<string, string>
     */
    private static function rentalCategoryOverrides(): array
    {
        return [
            'toyota|corolla' => 'Sedan',
            'toyota|camry' => 'Sedan',
            'toyota|yaris' => 'Economy',
            'toyota|wish' => 'Van',
            'toyota|noah' => 'Van',
            'toyota|voxy' => 'Van',
            'honda|civic' => 'Sedan',
            'honda|accord' => 'Sedan',
            'honda|fit' => 'Economy',
            'nissan|leaf' => 'Economy',
            'nissan|note' => 'Economy',
            'nissan|sunny' => 'Sedan',
            'hyundai|ioniq 5' => 'SUV',
            'hyundai|ioniq 6' => 'Sedan',
            'kia|ev6' => 'SUV',
            'kia|ev9' => 'SUV',
            'kia|niro ev' => 'SUV',
            'mercedes-benz|eqa' => 'SUV',
            'mercedes-benz|eqb' => 'SUV',
            'mercedes-benz|eqc' => 'SUV',
            'mercedes-benz|eqe' => 'Sedan',
            'mercedes-benz|eqs' => 'Sedan',
            'mercedes-benz|eqs suv' => 'SUV',
            'bmw|ix' => 'SUV',
            'volkswagen|id.3' => 'Economy',
            'volkswagen|id.4' => 'SUV',
            'volkswagen|id.5' => 'SUV',
            'volkswagen|id.7' => 'Sedan',
            'volkswagen|id.buzz' => 'Van',
            'ford|mustang mach-e' => 'SUV',
            'ford|e-transit' => 'Van',
            'mazda|mx-30' => 'SUV',
            'tesla|model 3' => 'Sedan',
            'tesla|model s' => 'Sedan',
            'tesla|model y' => 'SUV',
            'tesla|model x' => 'SUV',
            'tesla|cybertruck' => 'Pickup',
            'byd|atto 3' => 'SUV',
            'byd|dolphin' => 'Economy',
            'byd|seal' => 'Sedan',
            'mg|mg4' => 'Economy',
            'mg|zs ev' => 'SUV',
            'mg|mg5 ev' => 'Sedan',
            'mini|cooper' => 'Economy',
            'mini|cooper se' => 'Economy',
            'mini|countryman' => 'SUV',
            'mini|countryman se' => 'SUV',
            'mini|clubman' => 'Economy',
            'polestar|2' => 'Sedan',
            'polestar|3' => 'SUV',
            'polestar|4' => 'SUV',
            'peugeot|208' => 'Economy',
            'peugeot|e-208' => 'Economy',
            'peugeot|308' => 'Economy',
            'peugeot|e-308' => 'Economy',
            'peugeot|301' => 'Sedan',
            'peugeot|2008' => 'SUV',
            'peugeot|e-2008' => 'SUV',
            'peugeot|3008' => 'SUV',
            'peugeot|e-3008' => 'SUV',
            'peugeot|5008' => 'SUV',
            'renault|zoe' => 'Economy',
            'renault|scenic e-tech' => 'SUV',
            'subaru|outback' => 'SUV',
            'subaru|xv' => 'SUV',
            'vinfast|vf 5' => 'Economy',
            'vinfast|vf 6' => 'SUV',
            'vinfast|vf 7' => 'SUV',
            'vinfast|vf 8' => 'SUV',
            'vinfast|vf 9' => 'SUV',
        ];
    }

    private static function inferRentalCategory(string $model): string
    {
        if (self::modelMatches($model, [
            'hilux', 'navara', 'ranger', 'amarok', 'd-max', 'dmax', 'l200', 'triton',
            'bt-50', 'bt50', 'cannon', 'colorado', 'silverado', 'sierra', 'tundra',
            'tacoma', 'ridgeline', 'frontier', 'f-150', 'f150', 'f-250', 'f250',
            'cybertruck', 'gladiator',
        ])) {
            return 'Pickup';
        }

        if (self::modelMatches($model, [
            'hiace', 'vito', 'sprinter', 'transit', 'caravan', 'carnival', 'sienna',
            'odyssey', 'previa', 'alphard', 'voxy', 'noah', 'serena', 'staria',
            'urvan', 'nv200', 'nv350', 'proace', 'trafic', 'vivaro', 'crafter',
            'transporter', 'multivan', 'buzz', 'partner', 'berlingo', 'doblo',
            'kangoo', 'wish', 'townace', 'liteace', 'e-transit', 'evito', 'esprinter',
        ])) {
            return 'Van';
        }

        if (self::modelMatches($model, [
            'rav4', 'cr-v', 'crv', 'hr-v', 'hrv', 'x-trail', 'xtrail', 'qashqai',
            'tucson', 'santa fe', 'santafe', 'sportage', 'sorento', 'seltos',
            'fortuner', 'land cruiser', 'prado', 'harrier', 'patrol', 'pajero',
            'outlander', 'asx', 'eclipse cross', 'tiguan', 'touareg', 'everest',
            'ecosport', 'territory', 'cx-3', 'cx-5', 'cx-9', 'cx3', 'cx5', 'cx9',
            'captiva', 'trailblazer', 'equinox', 'macan', 'cayenne', 'model y',
            'model x', 'atto 3', 'sealion', 'tang', 'song plus', 'countryman',
            'aceman', 'arona', 'ateca', 'tavascan', 'duster', 'captur', 'kadjar',
            'kodiaq', 'karoq', 'enyaq', 'elroq', 'f-pace', 'e-pace', 'i-pace',
            'stelvio', 'tonale', 'mu-x', 'mux', 'jimny', 'vitara', 'ioniq 5',
            'ev9', 'niro', 'prologue', 'bz4x', 'defender', 'discovery',
            'range rover', 'evoque', 'renegade', 'compass', 'cherokee', 'wrangler',
            'avenger', 'forester', 'solterra', 'aircross', 'xc40', 'xc60', 'xc90',
            'ex30', 'ex40', 'ec40', 'ex90', 'q3', 'q5', 'q7', 'q8', 'q4', 'q6',
            'gla', 'glb', 'glc', 'gle', 'gls', 'g-class', 'eqa', 'eqb', 'eqc',
            'x1', 'x3', 'x5', 'x6', 'ix1', 'ix3', 'highlander', '4runner',
            'creta', 'kona', 'palisade', 'telluride', 'pilot', 'pathfinder',
            'murano', 'rogue', 'atlas', 'taos', 't-roc', 't-cross', 't-roc',
            'id.4', 'id.5', 'mach-e', 'bolt euv', 'marvel r', 'born',
            'junior', 'tonale', 'vf 6', 'vf 7', 'vf 8', 'vf 9',
        ])) {
            return 'SUV';
        }

        if (self::modelMatches($model, [
            'swift', 'alto', 'picanto', 'i10', 'i20', 'yaris', 'fit', 'polo',
            'fiesta', 'spark', 'celerio', 'aygo', 'corsa', 'sandero', 'fabia',
            'ibiza', 'rio', 'micra', 'march', 'note', 'leaf', 'cooper', 'twingo',
            'panda', 'mirage', 'mazda2', 'clio', 'golf', 'id.3', 'zoe', 'vf 5',
            'dzire', 'logan', 'c3', 'ë-c3', 'e-c3', '500', '500e', 'up',
            'bolt ev', 'mg4', 'dolphin', 'spring', 'i3', 'aygo',
        ])) {
            return 'Economy';
        }

        return 'Sedan';
    }

    /**
     * @param  list<string>  $needles
     */
    private static function modelMatches(string $model, array $needles): bool
    {
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $model) ?? $model));
        $tokens = array_values(array_filter(explode(' ', $normalized), fn (string $token) => $token !== ''));
        $compact = str_replace(' ', '', $normalized);
        $padded = ' '.$normalized.' ';

        foreach ($needles as $needle) {
            $needleNorm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $needle) ?? $needle));
            if ($needleNorm === '') {
                continue;
            }

            $needleTokens = array_values(array_filter(explode(' ', $needleNorm)));
            $needleCompact = str_replace(' ', '', $needleNorm);

            if (count($needleTokens) > 1) {
                if (str_contains($padded, ' '.$needleNorm.' ')) {
                    return true;
                }

                continue;
            }

            if (strlen($needleCompact) <= 3) {
                if (in_array($needleCompact, $tokens, true)) {
                    return true;
                }

                continue;
            }

            if (in_array($needleCompact, $tokens, true) || $compact === $needleCompact) {
                return true;
            }

            if (strlen($needleCompact) >= 4 && preg_match('/(?:^| )'.preg_quote($needleNorm, '/').'(?: |$)/', $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Catalog makes plus any already stored on a vehicle (edit / legacy).
     *
     * @return list<string>
     */
    public static function makeOptions(?Vehicle $vehicle = null): array
    {
        $makes = self::makes();

        if ($vehicle?->make && ! in_array($vehicle->make, $makes, true)) {
            $makes[] = $vehicle->make;
            natcasesort($makes);
            $makes = array_values($makes);
        }

        return $makes;
    }

    /**
     * Catalog models for a make, plus the vehicle's current model when editing.
     *
     * @return list<string>
     */
    public static function modelOptions(?string $make, ?Vehicle $vehicle = null): array
    {
        $models = self::modelsFor($make);

        if (
            $vehicle?->model
            && $vehicle->make === $make
            && ! in_array($vehicle->model, $models, true)
        ) {
            $models[] = $vehicle->model;
            natcasesort($models);
            $models = array_values($models);
        }

        return $models;
    }

    public static function isValidMake(string $make, ?Vehicle $vehicle = null): bool
    {
        return in_array($make, self::makeOptions($vehicle), true);
    }

    public static function isValidModel(string $make, string $model, ?Vehicle $vehicle = null): bool
    {
        return in_array($model, self::modelOptions($make, $vehicle), true);
    }
}
