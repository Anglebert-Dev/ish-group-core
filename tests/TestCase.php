<?php

namespace Tests;

use App\Enums\CustomerStatus;
use App\Enums\FuelType;
use App\Enums\LicenceCategory;
use App\Enums\TransmissionType;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Libraries\RwandaPlateLibrary;
use App\Models\Customer;
use App\Models\Station\Station;
use App\Models\User;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use App\Services\Permission\PermissionService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const TEST_PASSWORD = 'SecurePass1!';

    private static int $seq = 0;

    protected function nextSeq(): int
    {
        return ++self::$seq;
    }

    protected function actingAsStaff(?User $user = null): User
    {
        $user ??= $this->makeStaff();

        $this->actingAs($user, 'staff');

        return $user;
    }

    protected function actingAsCustomer(?Customer $customer = null): Customer
    {
        $customer ??= $this->makeCustomer();

        $this->actingAs($customer, 'customer');

        return $customer;
    }

    protected function makeStaff(array $overrides = []): User
    {
        $user = new User([
            'name' => $overrides['name'] ?? 'Staff User',
            'email' => $overrides['email'] ?? 'staff@example.com',
            'password' => Hash::make($overrides['password'] ?? self::TEST_PASSWORD),
        ]);
        $user->status = $overrides['status'] ?? UserStatus::Active;
        $user->save();

        app(PermissionService::class)->initPermissions(true);
        $user->assignRole('SuperAdmin');

        return $user->fresh();
    }

    protected function makeCustomer(array $overrides = []): Customer
    {
        $seq = $this->nextSeq();
        $customer = new Customer([
            'name' => $overrides['name'] ?? 'Jane Renter',
            'email' => $overrides['email'] ?? 'jane@example.com',
            'phone' => $overrides['phone'] ?? '+250788000000',
            'national_id' => $overrides['national_id'] ?? sprintf('1199%012d', $seq),
            'address' => $overrides['address'] ?? 'Kigali, Rwanda',
            'driver_license_number' => $overrides['driver_license_number'] ?? 'DL-'.$seq,
            'password' => Hash::make($overrides['password'] ?? self::TEST_PASSWORD),
        ]);
        $customer->status = $overrides['status'] ?? CustomerStatus::Active;
        $customer->save();

        return $customer->fresh();
    }

    protected function makeStation(array $overrides = []): Station
    {
        $seq = $this->nextSeq();
        $station = new Station([
            'name' => $overrides['name'] ?? 'Station '.$seq,
            'code' => $overrides['code'] ?? 'S'.$seq,
            'city' => $overrides['city'] ?? 'Kigali',
            'status' => $overrides['status'] ?? 'active',
        ]);
        $station->save();

        return $station->fresh();
    }

    protected function makeCategory(array $overrides = []): VehicleCategory
    {
        $category = new VehicleCategory([
            'name' => $overrides['name'] ?? 'Economy',
            'description' => $overrides['description'] ?? 'Daily city cars',
            'daily_price' => $overrides['daily_price'] ?? '50000.00',
            'status' => $overrides['status'] ?? 'active',
        ]);
        $category->save();

        return $category->fresh();
    }

    protected function makeVehicle(?VehicleCategory $category = null, ?Station $station = null, array $overrides = []): Vehicle
    {
        $category ??= $this->makeCategory();
        $station ??= $this->makeStation();

        $seq = $this->nextSeq();
        $plate = $overrides['plate_number'] ?? sprintf('RAD %03d A', $seq);
        $vehicle = new Vehicle([
            'make' => $overrides['make'] ?? 'Toyota',
            'model' => $overrides['model'] ?? 'Corolla',
            'year' => $overrides['year'] ?? 2022,
            'plate_number' => RwandaPlateLibrary::normalize($plate) ?? $plate,
            'vehicle_category_id' => $category->id,
            'daily_price' => $overrides['daily_price'] ?? $category->daily_price,
            'station_id' => $station->id,
            'vehicle_owner_id' => $overrides['vehicle_owner_id'] ?? null,
            'vehicle_type' => $overrides['vehicle_type'] ?? VehicleType::Sedan->value,
            'seats' => $overrides['seats'] ?? 5,
            'transmission' => $overrides['transmission'] ?? TransmissionType::Automatic->value,
            'fuel_type' => $overrides['fuel_type'] ?? FuelType::Petrol->value,
            'required_license_category' => $overrides['required_license_category'] ?? LicenceCategory::B->value,
            'notes' => $overrides['notes'] ?? 'Well-maintained rental vehicle ready for your trip around Rwanda.',
            'status' => $overrides['status'] ?? VehicleStatus::Available->value,
        ]);
        $vehicle->save();

        return $vehicle->fresh();
    }

    protected function seedChartOfAccounts(): void
    {
        $this->seed(ChartOfAccountSeeder::class);
    }
}
