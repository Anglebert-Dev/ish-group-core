<?php

namespace Database\Seeders;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentType;
use App\Enums\DriverStatus;
use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\FuelType;
use App\Enums\LicenceCategory;
use App\Enums\StationStatus;
use App\Enums\TransmissionType;
use App\Enums\VehicleCategoryStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Driver\Driver;
use App\Models\Extra\Extra;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleImage;
use App\Models\VehicleCategory\VehicleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoFleetSeeder extends Seeder
{
    public function run(): void
    {
        $stations = $this->seedStations();
        $categories = $this->seedCategories();
        $this->seedExtras();
        $this->seedDrivers();
        $this->seedVehicles($stations, $categories);
    }

    /**
     * @return array<string, Station>
     */
    private function seedStations(): array
    {
        $rows = [
            [
                'code' => 'KGL',
                'name' => 'Kigali International Airport',
                'phone' => '+250 788 100 001',
                'email' => 'airport@example.com',
                'address' => 'Kigali International Airport',
                'city' => 'Kigali',
                'opening_time' => '06:00:00',
                'closing_time' => '22:00:00',
            ],
            [
                'code' => 'KCY',
                'name' => 'Kigali City Centre',
                'phone' => '+250 788 100 002',
                'email' => 'city@example.com',
                'address' => 'KN 4 Ave, Nyarugenge',
                'city' => 'Kigali',
                'opening_time' => '07:00:00',
                'closing_time' => '19:00:00',
            ],
            [
                'code' => 'RBV',
                'name' => 'Rubavu Station',
                'phone' => '+250 788 100 003',
                'email' => 'rubavu@example.com',
                'address' => 'Gisenyi Road',
                'city' => 'Rubavu',
                'opening_time' => '07:00:00',
                'closing_time' => '18:00:00',
            ],
        ];

        $stations = [];

        foreach ($rows as $row) {
            $station = Station::query()->firstOrNew(['code' => $row['code']]);
            $station->fill($row + ['status' => StationStatus::Active]);
            $station->save();
            $stations[$row['code']] = $station;
        }

        return $stations;
    }

    /**
     * @return array<string, VehicleCategory>
     */
    private function seedCategories(): array
    {
        $rows = [
            [
                'name' => 'Economy',
                'description' => 'Compact cars for city trips and short stays.',
                'daily_price' => 35000,
                'image' => 'hatchback.jpg',
            ],
            [
                'name' => 'Sedan',
                'description' => 'Comfortable saloons for business and airport transfers.',
                'daily_price' => 55000,
                'image' => 'sedan-black.jpg',
            ],
            [
                'name' => 'SUV',
                'description' => 'Higher clearance for family travel and upcountry roads.',
                'daily_price' => 85000,
                'image' => 'suv-white.jpg',
            ],
            [
                'name' => 'Van',
                'description' => 'Passenger vans for groups, hotels, and staff transport.',
                'daily_price' => 95000,
                'image' => 'van.jpg',
            ],
            [
                'name' => 'Pickup',
                'description' => 'Double-cab pickups for cargo and site work.',
                'daily_price' => 75000,
                'image' => 'pickup.jpg',
            ],
        ];

        $categories = [];

        foreach ($rows as $row) {
            $image = $row['image'];
            unset($row['image']);

            $category = VehicleCategory::query()->firstOrNew(['name' => $row['name']]);
            $category->fill($row + ['status' => VehicleCategoryStatus::Active]);

            if (! $category->image_path) {
                $category->image_path = $this->storePublicImage($this->seedImage($image), 'vehicle-categories');
            }

            $category->save();
            $categories[$row['name']] = $category;
        }

        return $categories;
    }

    private function seedExtras(): void
    {
        $rows = [
            ['name' => 'Child seat', 'description' => 'Forward-facing child seat.', 'price' => 5000],
            ['name' => 'GPS navigator', 'description' => 'Handheld GPS with Rwanda maps.', 'price' => 3000],
            ['name' => 'Additional driver', 'description' => 'Add a second named driver to the rental.', 'price' => 10000],
            ['name' => 'Roof rack', 'description' => 'Roof bars for luggage or safari gear.', 'price' => 4000],
            ['name' => 'Wi-Fi hotspot', 'description' => 'In-car mobile hotspot for the rental period.', 'price' => 3500],
            ['name' => 'Full cover', 'description' => 'Reduced excess insurance for the trip.', 'price' => 15000],
            [
                'name' => 'Chauffeur service',
                'description' => 'Professional driver billed per rental day when the customer selects With Driver.',
                'price' => 25000,
                'type' => ExtraType::Chauffeur,
            ],
        ];

        foreach ($rows as $row) {
            Extra::query()->firstOrCreate(
                ['name' => $row['name']],
                $row + [
                    'status' => ExtraStatus::Active,
                    'type' => $row['type'] ?? ExtraType::Optional,
                ],
            );
        }
    }

    private function seedDrivers(): void
    {
        $rows = [
            [
                'first_name' => 'Jean',
                'last_name' => 'Uwimana',
                'phone' => '+250 788 200 001',
                'email' => 'jean.uwimana@example.com',
                'address' => 'Kacyiru, Kigali',
                'license_number' => 'RW-DL-1001',
                'license_expiry' => '2028-04-15',
                'license_category' => LicenceCategory::B,
                'daily_rate' => 25000,
                'employment_status' => DriverEmploymentType::Employed,
            ],
            [
                'first_name' => 'Aline',
                'last_name' => 'Mukamana',
                'phone' => '+250 788 200 002',
                'email' => 'aline.mukamana@example.com',
                'address' => 'Kimironko, Kigali',
                'license_number' => 'RW-DL-1002',
                'license_expiry' => '2027-11-02',
                'license_category' => LicenceCategory::B,
                'daily_rate' => 25000,
                'employment_status' => DriverEmploymentType::Employed,
            ],
            [
                'first_name' => 'Eric',
                'last_name' => 'Niyonzima',
                'phone' => '+250 788 200 003',
                'email' => 'eric.niyonzima@example.com',
                'address' => 'Remera, Kigali',
                'license_number' => 'RW-DL-1003',
                'license_expiry' => '2029-01-20',
                'license_category' => LicenceCategory::D1,
                'daily_rate' => 30000,
                'employment_status' => DriverEmploymentType::Employed,
            ],
            [
                'first_name' => 'Claudine',
                'last_name' => 'Uwase',
                'phone' => '+250 788 200 004',
                'email' => 'claudine.uwase@example.com',
                'address' => 'Gisozi, Kigali',
                'license_number' => 'RW-DL-1004',
                'license_expiry' => '2026-08-30',
                'license_category' => LicenceCategory::B,
                'daily_rate' => 28000,
                'employment_status' => DriverEmploymentType::Contractor,
            ],
            [
                'first_name' => 'Patrick',
                'last_name' => 'Habimana',
                'phone' => '+250 788 200 005',
                'email' => 'patrick.habimana@example.com',
                'address' => 'Nyamirambo, Kigali',
                'license_number' => 'RW-DL-1005',
                'license_expiry' => '2028-06-12',
                'license_category' => LicenceCategory::C,
                'daily_rate' => 27000,
                'employment_status' => DriverEmploymentType::Employed,
            ],
            [
                'first_name' => 'Diane',
                'last_name' => 'Iradukunda',
                'phone' => '+250 788 200 006',
                'email' => 'diane.iradukunda@example.com',
                'address' => 'Rubavu',
                'license_number' => 'RW-DL-1006',
                'license_expiry' => '2027-03-18',
                'license_category' => LicenceCategory::B,
                'daily_rate' => 24000,
                'employment_status' => DriverEmploymentType::Contractor,
                'availability_status' => DriverAvailabilityStatus::OffDuty,
            ],
        ];

        foreach ($rows as $row) {
            $driver = Driver::query()->firstOrNew(['license_number' => $row['license_number']]);
            $employment = $row['employment_status'];
            $availability = $row['availability_status'] ?? DriverAvailabilityStatus::Available;
            unset($row['employment_status'], $row['availability_status']);

            $driver->fill($row);
            $driver->employment_status = $employment;
            $driver->availability_status = $availability;
            $driver->status = DriverStatus::Active;
            $driver->save();
        }
    }

    /**
     * @param  array<string, Station>  $stations
     * @param  array<string, VehicleCategory>  $categories
     */
    private function seedVehicles(array $stations, array $categories): void
    {
        // Category daily_price is only a suggested default; each car stores its own rate.
        // `notes` is the public "Vehicle Description" shown on the renter detail page.
        $rows = [
            [
                'plate_number' => 'RAD 101 A',
                'make' => 'Suzuki',
                'model' => 'Swift',
                'year' => 2021,
                'vin' => 'JSAAZC83S00101101',
                'color' => 'White',
                'category' => 'Economy',
                'daily_price' => 32000,
                'station' => 'KCY',
                'vehicle_type' => VehicleType::Hatchback,
                'seats' => 5,
                'transmission' => TransmissionType::Manual,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '1.2L',
                'mileage' => 28400,
                'notes' => 'A nimble Suzuki Swift hatchback built for Kigali city driving. Easy to park, light on fuel, and comfortable for daily errands or short getaways. Manual gearbox with seating for five.',
                'images' => ['hatchback.jpg', 'sedan-white.jpg'],
            ],
            [
                'plate_number' => 'RAD 102 A',
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2022,
                'vin' => 'JTDBR32E50101102',
                'color' => 'Silver',
                'category' => 'Sedan',
                'daily_price' => 52000,
                'station' => 'KGL',
                'vehicle_type' => VehicleType::Sedan,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '1.8L',
                'mileage' => 19250,
                'notes' => 'Reliable Toyota Corolla sedan with automatic transmission — a favourite for airport transfers and business trips. Quiet cabin, efficient petrol engine, and space for five passengers plus luggage.',
                'images' => ['sedan-silver.jpg', 'sedan-white.jpg'],
            ],
            [
                'plate_number' => 'RAD 103 A',
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2023,
                'vin' => '4T1B11HK5PU101103',
                'color' => 'White',
                'category' => 'Sedan',
                'daily_price' => 65000,
                'station' => 'KGL',
                'vehicle_type' => VehicleType::Sedan,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Hybrid,
                'engine_capacity' => '2.5L',
                'mileage' => 8700,
                'notes' => 'Smooth Toyota Camry hybrid for a quieter, more efficient drive. Automatic gearbox, refined ride, and ample boot space — well suited to longer transfers and executive travel around Rwanda.',
                'images' => ['sedan-white.jpg', 'sedan-silver.jpg'],
            ],
            [
                'plate_number' => 'RAD 104 A',
                'make' => 'Mercedes-Benz',
                'model' => 'E-Class',
                'year' => 2022,
                'vin' => 'WDDZF4JB4NA101104',
                'color' => 'Black',
                'category' => 'Sedan',
                'daily_price' => 98000,
                'station' => 'KCY',
                'vehicle_type' => VehicleType::Sedan,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '2.0L',
                'mileage' => 14320,
                'notes' => 'Premium Mercedes-Benz E-Class sedan with automatic transmission. Soft leather seating, strong road presence, and a quiet cabin — ideal when you want a polished chauffeur or self-drive experience.',
                'images' => ['sedan-black.jpg', 'sedan-bmw.jpg'],
            ],
            [
                'plate_number' => 'RAD 105 A',
                'make' => 'BMW',
                'model' => '3 Series',
                'year' => 2021,
                'vin' => 'WBA5R1C05MF101105',
                'color' => 'Blue',
                'category' => 'Sedan',
                'daily_price' => 88000,
                'station' => 'KCY',
                'vehicle_type' => VehicleType::Sedan,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '2.0L',
                'mileage' => 22100,
                'status' => VehicleStatus::Maintenance,
                'notes' => 'Sporty BMW 3 Series sedan with automatic gearbox and a responsive petrol engine. Sharp handling and a premium interior for business travel or weekend escapes once it returns from scheduled service.',
                'images' => ['sedan-bmw.jpg', 'sedan-black.jpg'],
            ],
            [
                'plate_number' => 'RAD 201 A',
                'make' => 'Toyota',
                'model' => 'RAV4',
                'year' => 2023,
                'vin' => 'JTMB1RFV0P1010201',
                'color' => 'White',
                'category' => 'SUV',
                'daily_price' => 82000,
                'station' => 'KGL',
                'vehicle_type' => VehicleType::Suv,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '2.5L',
                'mileage' => 11200,
                'notes' => 'Modern Toyota RAV4 SUV with automatic transmission and higher ground clearance. Comfortable for city roads and light upcountry travel, with room for family luggage and five passengers.',
                'images' => ['suv-white.jpg', 'crossover.jpg'],
            ],
            [
                'plate_number' => 'RAD 202 A',
                'make' => 'Toyota',
                'model' => 'Land Cruiser Prado',
                'year' => 2021,
                'vin' => 'JTEBU9FJ5M1010202',
                'color' => 'Grey',
                'category' => 'SUV',
                'daily_price' => 125000,
                'station' => 'RBV',
                'vehicle_type' => VehicleType::Suv,
                'seats' => 7,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Diesel,
                'engine_capacity' => '2.8L',
                'mileage' => 46800,
                'required_license_category' => LicenceCategory::B,
                'notes' => 'Capable Toyota Land Cruiser Prado with seven seats and a strong diesel engine. Built for longer journeys, national parks, and rough stretches — automatic gearbox and ample cargo space for adventure travel.',
                'images' => ['suv-dark.jpg', 'suv-adventure.jpg'],
            ],
            [
                'plate_number' => 'RAD 203 A',
                'make' => 'Honda',
                'model' => 'CR-V',
                'year' => 2022,
                'vin' => '5J6RW2H89NL101203',
                'color' => 'White',
                'category' => 'SUV',
                'daily_price' => 78000,
                'station' => 'KCY',
                'vehicle_type' => VehicleType::Crossover,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Petrol,
                'engine_capacity' => '1.5L',
                'mileage' => 17440,
                'notes' => 'Versatile Honda CR-V crossover with automatic transmission. Smooth turbo petrol performance, high seating position, and a flexible boot — great for families exploring Kigali and nearby towns.',
                'images' => ['crossover.jpg', 'suv-white.jpg'],
            ],
            [
                'plate_number' => 'RAD 301 A',
                'make' => 'Toyota',
                'model' => 'Hiace',
                'year' => 2020,
                'vin' => 'JTFHX02P5L1010301',
                'color' => 'White',
                'category' => 'Van',
                'daily_price' => 98000,
                'station' => 'KGL',
                'vehicle_type' => VehicleType::Van,
                'seats' => 14,
                'transmission' => TransmissionType::Manual,
                'fuel_type' => FuelType::Diesel,
                'engine_capacity' => '2.8L',
                'mileage' => 81200,
                'required_license_category' => LicenceCategory::D1,
                'notes' => 'Spacious Toyota Hiace passenger van seating up to 14. Diesel power and a manual gearbox make it practical for hotel shuttles, staff transport, and group trips across Rwanda.',
                'images' => ['van.jpg', 'suv-adventure.jpg'],
            ],
            [
                'plate_number' => 'RAD 401 A',
                'make' => 'Toyota',
                'model' => 'Hilux',
                'year' => 2023,
                'vin' => 'AHTEZ3GN8P1010401',
                'color' => 'White',
                'category' => 'Pickup',
                'daily_price' => 80000,
                'station' => 'RBV',
                'vehicle_type' => VehicleType::Pickup,
                'seats' => 5,
                'transmission' => TransmissionType::Automatic,
                'fuel_type' => FuelType::Diesel,
                'engine_capacity' => '2.8L',
                'mileage' => 15600,
                'required_license_category' => LicenceCategory::B,
                'notes' => 'Tough Toyota Hilux double-cab pickup with automatic diesel power. Ready for cargo, site visits, and rough roads, with a comfortable cabin for five when you need both people and payload.',
                'images' => ['pickup.jpg', 'suv-adventure.jpg'],
            ],
        ];

        foreach ($rows as $row) {
            $images = $row['images'];
            $categoryName = $row['category'];
            $stationCode = $row['station'];
            unset($row['images'], $row['category'], $row['station']);

            $vehicle = Vehicle::query()->firstOrNew(['plate_number' => $row['plate_number']]);
            $vehicle->fill($row + [
                'vehicle_category_id' => $categories[$categoryName]->id,
                'daily_price' => $row['daily_price'] ?? $categories[$categoryName]->daily_price,
                'station_id' => $stations[$stationCode]->id,
                'required_license_category' => $row['required_license_category'] ?? LicenceCategory::B,
                'status' => $row['status'] ?? VehicleStatus::Available,
                'notes' => $row['notes'] ?? $this->defaultVehicleDescription($row),
            ]);
            $vehicle->save();

            $this->attachVehicleImages($vehicle, $images);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function defaultVehicleDescription(array $row): string
    {
        $label = trim(($row['make'] ?? '').' '.($row['model'] ?? '')) ?: 'This vehicle';
        $year = $row['year'] ?? null;
        $seats = $row['seats'] ?? null;
        $transmission = $row['transmission'] instanceof TransmissionType
            ? $row['transmission']->label()
            : (string) ($row['transmission'] ?? 'automatic');
        $fuel = $row['fuel_type'] instanceof FuelType
            ? $row['fuel_type']->label()
            : (string) ($row['fuel_type'] ?? 'petrol');

        $parts = [
            $year ? "{$year} {$label}" : $label,
            "with {$transmission} transmission and {$fuel} fuel",
        ];

        if ($seats) {
            $parts[] = "seating up to {$seats} passengers";
        }

        return implode(', ', $parts).'. Well maintained and ready for your rental in Rwanda.';
    }

    /**
     * @param  list<string>  $filenames
     */
    private function attachVehicleImages(Vehicle $vehicle, array $filenames): void
    {
        if ($vehicle->images()->exists()) {
            return;
        }

        foreach (array_values($filenames) as $index => $filename) {
            $path = $this->storePublicImage($this->seedImage($filename), 'vehicles/'.$vehicle->uuid);

            VehicleImage::create([
                'vehicle_id' => $vehicle->id,
                'file_path' => $path,
                'sort_order' => $index,
            ]);
        }
    }

    private function seedImage(string $filename): string
    {
        $path = database_path('seeders/images/vehicles/'.$filename);

        if (! is_file($path)) {
            throw new \RuntimeException("Missing seed image [{$filename}] at {$path}");
        }

        return $path;
    }

    private function storePublicImage(string $source, string $directory): string
    {
        $destination = trim($directory, '/').'/'.(string) Str::uuid().'.jpg';
        Storage::disk('public')->put($destination, File::get($source));

        return $destination;
    }
}
