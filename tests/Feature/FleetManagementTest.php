<?php

namespace Tests\Feature;

use App\Enums\FuelLogStatus;
use App\Enums\FuelType;
use App\Enums\LicenceCategory;
use App\Enums\TransmissionType;
use App\Enums\VehicleOwnershipType;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Driver\Driver;
use App\Models\FuelLog\FuelLog;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleOwner\VehicleOwner;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    public function test_staff_can_create_a_station_and_category(): void
    {
        $this->actingAsStaff();

        $this->post(route('stations.store'), [
            'name' => 'Kigali City',
            'code' => 'KCY',
            'city' => 'Kigali',
            'status' => 'active',
        ])->assertRedirect(route('stations.index'));

        $this->assertDatabaseHas('stations', ['code' => 'KCY']);

        $this->post(route('fleet.categories.store'), [
            'name' => 'SUV',
            'description' => 'Family SUVs',
            'daily_price' => '85000',
            'status' => 'active',
        ])->assertRedirect(route('fleet.categories.index'));

        $this->assertDatabaseHas('vehicle_categories', [
            'name' => 'SUV',
            'daily_price' => '85000.00',
        ]);
    }

    public function test_staff_can_create_a_company_owned_vehicle(): void
    {
        $this->actingAsStaff();
        $station = $this->makeStation();
        $category = $this->makeCategory();

        $this->post(route('fleet.vehicles.store'), [
            'make' => 'Honda',
            'model' => 'CR-V',
            'year' => 2023,
            'plate_number' => 'RAD777B',
            'vehicle_category_id' => $category->id,
            'daily_price' => '92000',
            'station_id' => $station->id,
            'ownership_type' => VehicleOwnershipType::Company->value,
            'vehicle_type' => VehicleType::Suv->value,
            'seats' => 5,
            'transmission' => TransmissionType::Automatic->value,
            'fuel_type' => FuelType::Petrol->value,
            'required_license_category' => LicenceCategory::B->value,
            'status' => VehicleStatus::Available->value,
        ])->assertRedirect(route('fleet.vehicles.index'));

        $vehicle = Vehicle::query()->where('plate_number', 'RAD 777 B')->first();
        $this->assertNotNull($vehicle);
        $this->assertNull($vehicle->vehicle_owner_id);
        $this->assertTrue($vehicle->isCompanyOwned());
        $this->assertSame('92000.00', (string) $vehicle->daily_price);
        $this->assertSame('92000.00', (string) $vehicle->dailyPrice());
        $this->assertNotSame((string) $category->daily_price, (string) $vehicle->dailyPrice());
    }

    public function test_outsider_vehicle_requires_an_owner(): void
    {
        $this->actingAsStaff();
        $station = $this->makeStation();
        $category = $this->makeCategory();

        $this->post(route('fleet.vehicles.store'), [
            'make' => 'Toyota',
            'model' => 'RAV4',
            'year' => 2021,
            'plate_number' => 'RAD888C',
            'vehicle_category_id' => $category->id,
            'daily_price' => '85000',
            'station_id' => $station->id,
            'ownership_type' => VehicleOwnershipType::Outsider->value,
            'vehicle_type' => VehicleType::Suv->value,
            'seats' => 5,
            'transmission' => TransmissionType::Automatic->value,
            'fuel_type' => FuelType::Petrol->value,
        ])->assertSessionHasErrors('vehicle_owner_id');
    }

    public function test_staff_can_create_an_owner_and_attach_a_vehicle(): void
    {
        $this->actingAsStaff();
        $station = $this->makeStation();
        $category = $this->makeCategory();

        $this->post(route('fleet.owners.store'), [
            'first_name' => 'Paul',
            'last_name' => 'Owner',
            'phone' => '+250788333333',
            'email' => 'owner@example.com',
            'national_id' => '1199880012345678',
            'owner_share_percent' => 60,
            'company_share_percent' => 40,
        ])->assertRedirect(route('fleet.owners.index'));

        $owner = VehicleOwner::query()->where('email', 'owner@example.com')->first();
        $this->assertNotNull($owner);
        $this->assertSame('Paul Owner', $owner->fullName());

        $this->post(route('fleet.owners.store'), [
            'first_name' => 'Broken',
            'last_name' => 'Split',
            'phone' => '+250788333334',
            'national_id' => '1199880012345679',
            'owner_share_percent' => 60,
            'company_share_percent' => 30,
        ])->assertSessionHasErrors('company_share_percent');

        $this->post(route('fleet.vehicles.store'), [
            'make' => 'Nissan',
            'model' => 'X-Trail',
            'year' => 2020,
            'plate_number' => 'RAD444D',
            'vehicle_category_id' => $category->id,
            'daily_price' => '88000',
            'station_id' => $station->id,
            'ownership_type' => VehicleOwnershipType::Outsider->value,
            'vehicle_owner_id' => $owner->id,
            'vehicle_type' => VehicleType::Suv->value,
            'seats' => 5,
            'transmission' => TransmissionType::Automatic->value,
            'fuel_type' => FuelType::Petrol->value,
            'status' => VehicleStatus::Available->value,
        ])->assertRedirect(route('fleet.vehicles.index'));

        $vehicle = Vehicle::query()->where('plate_number', 'RAD 444 D')->first();
        $this->assertSame($owner->id, $vehicle->vehicle_owner_id);
        $this->assertFalse($vehicle->isCompanyOwned());
        $this->assertSame('Paul Owner', $vehicle->ownershipLabel());
        $this->assertSame('88000.00', (string) $vehicle->daily_price);
    }

    public function test_vehicle_plate_number_must_use_rwandan_format(): void
    {
        $this->actingAsStaff();
        $station = $this->makeStation();
        $category = $this->makeCategory();

        $payload = [
            'make' => 'Toyota',
            'model' => 'RAV4',
            'year' => 2022,
            'vehicle_category_id' => $category->id,
            'daily_price' => '85000',
            'station_id' => $station->id,
            'ownership_type' => VehicleOwnershipType::Company->value,
            'vehicle_type' => VehicleType::Suv->value,
            'seats' => 5,
            'transmission' => TransmissionType::Automatic->value,
            'fuel_type' => FuelType::Petrol->value,
        ];

        $this->post(route('fleet.vehicles.store'), $payload + [
            'plate_number' => 'ABC-123',
        ])->assertSessionHasErrors('plate_number');

        $this->post(route('fleet.vehicles.store'), $payload + [
            'plate_number' => 'rad 321 b',
        ])->assertRedirect(route('fleet.vehicles.index'));

        $this->assertDatabaseHas('vehicles', ['plate_number' => 'RAD 321 B']);
    }

    public function test_staff_can_view_fleet_index_pages(): void
    {
        $this->actingAsStaff();
        $this->makeVehicle();

        $this->get(route('fleet.vehicles.index'))->assertOk();
        $this->get(route('fleet.categories.index'))->assertOk();
        $this->get(route('fleet.owners.index'))->assertOk();
        $this->get(route('stations.index'))->assertOk();
        $this->get(route('fleet.maintenance.index'))->assertOk();
        $this->get(route('fleet.fuel_logs.index'))->assertOk();
    }

    public function test_staff_can_fetch_catalog_models_for_a_make(): void
    {
        $this->actingAsStaff();

        $this->getJson(route('fleet.vehicles.catalog.models', ['make' => 'Toyota']))
            ->assertOk()
            ->assertJsonPath('make', 'Toyota')
            ->assertJsonFragment(['Corolla'])
            ->assertJsonPath('model_categories.RAV4', 'SUV')
            ->assertJsonPath('model_categories.Hilux', 'Pickup')
            ->assertJsonPath('model_categories.Hiace', 'Van')
            ->assertJsonPath('model_categories.Camry', 'Sedan')
            ->assertJsonPath('model_specs.RAV4.vehicle_type', 'crossover')
            ->assertJsonPath('model_specs.Hilux.fuel_type', 'diesel')
            ->assertJsonPath('model_specs.Hiace.seats', 14);
    }

    public function test_staff_can_log_fuel_and_update_vehicle_mileage(): void
    {
        $this->actingAsStaff();
        $vehicle = $this->makeVehicle();
        $vehicle->mileage = 12000;
        $vehicle->save();

        $driver = new Driver([
            'first_name' => 'Jean',
            'last_name' => 'Driver',
            'phone' => '+250788111222',
            'license_number' => 'DL-FUEL-1',
            'license_category' => LicenceCategory::B->value,
        ]);
        $driver->save();

        $this->post(route('fleet.fuel_logs.store'), [
            'vehicle_id' => $vehicle->id,
            'gas_station_name' => 'Kigali Total Energies',
            'driver_id' => $driver->id,
            'date' => '2026-09-10',
            'liters' => '42.50',
            'cost' => '65000',
            'mileage' => 12500,
            'status' => FuelLogStatus::Recorded->value,
            'notes' => 'Full tank before trip',
        ])->assertRedirect(route('fleet.fuel_logs.index'));

        $fuelLog = FuelLog::query()->where('vehicle_id', $vehicle->id)->first();
        $this->assertNotNull($fuelLog);
        $this->assertSame('42.50', (string) $fuelLog->liters);
        $this->assertSame('Kigali Total Energies', $fuelLog->gas_station_name);
        $this->assertSame($driver->id, $fuelLog->driver_id);
        $this->assertSame(FuelType::Petrol, $fuelLog->fuel_type);

        $vehicle->refresh();
        $this->assertSame(12500, $vehicle->mileage);
    }

    public function test_staff_can_update_vehicle_compliance_from_details(): void
    {
        $this->actingAsStaff();
        $vehicle = $this->makeVehicle();

        $this->put(route('fleet.vehicles.compliance', $vehicle), [
            'insurance_company' => 'SONARWA',
            'insurance_expires_at' => now()->addYear()->toDateString(),
            'inspection_done_at' => now()->subMonth()->toDateString(),
            'inspection_expires_at' => now()->addMonths(11)->toDateString(),
            'carte_jaune_number' => 'CJ-998877',
            'carte_jaune_issued_at' => now()->subYears(2)->toDateString(),
        ])->assertRedirect(route('fleet.vehicles.show', ['vehicle' => $vehicle, 'tab' => 'compliance']));

        $vehicle->refresh();
        $this->assertSame('SONARWA', $vehicle->insurance_company);
        $this->assertSame('CJ-998877', $vehicle->carte_jaune_number);
        $this->assertNotNull($vehicle->insurance_expires_at);
        $this->assertNotNull($vehicle->inspection_expires_at);
    }

    public function test_staff_can_update_driver_license_from_details(): void
    {
        $this->actingAsStaff();

        $driver = new Driver([
            'first_name' => 'Alice',
            'last_name' => 'Driver',
            'phone' => '+250788333444',
            'national_id' => '1199000098765432',
            'license_number' => 'DL-OLD-1',
            'license_expiry' => now()->subDays(5)->toDateString(),
            'license_category' => LicenceCategory::B->value,
        ]);
        $driver->save();

        $this->put(route('drivers.license', $driver), [
            'national_id' => '1199000098765432',
            'license_number' => 'DL-NEW-9',
            'license_expiry' => now()->addYear()->toDateString(),
            'license_category' => LicenceCategory::B->value,
        ])->assertRedirect(route('drivers.show', ['driver' => $driver, 'tab' => 'overview']));

        $driver->refresh();
        $this->assertSame('DL-NEW-9', $driver->license_number);
        $this->assertTrue($driver->license_expiry->isFuture());
        $this->assertFalse($driver->licenseAlertLevel()->isAttention());
    }
}
