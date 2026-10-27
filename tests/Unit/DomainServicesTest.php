<?php

namespace Tests\Unit;

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\RentalType;
use App\Enums\VehicleStatus;
use App\Models\Extra\Extra;
use App\Services\Availability\VehicleAvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Extra\ExtraService;
use Tests\TestCase;

class DomainServicesTest extends TestCase
{
    public function test_optional_catalog_excludes_chauffeur_extras(): void
    {
        $optional = new Extra([
            'name' => 'Wi-Fi hotspot',
            'price' => '3000.00',
            'type' => ExtraType::Optional->value,
            'status' => ExtraStatus::Active->value,
        ]);
        $optional->save();

        $inactive = new Extra([
            'name' => 'Inactive booster',
            'price' => '1000.00',
            'type' => ExtraType::Optional->value,
            'status' => ExtraStatus::Inactive->value,
        ]);
        $inactive->save();

        $catalog = app(ExtraService::class)->optionalCatalog();

        $this->assertTrue($catalog->contains('id', $optional->id));
        $this->assertFalse($catalog->contains('id', $inactive->id));
        $this->assertTrue($catalog->every(fn (Extra $extra) => $extra->type === ExtraType::Optional));
        $this->assertSame('25000.00', app(ExtraService::class)->chauffeurDailyFee());
        $this->assertNotNull(app(ExtraService::class)->chauffeurExtra());
        $this->assertTrue(app(ExtraService::class)->chauffeurExtra()->isChauffeur());
    }

    public function test_chauffeur_fee_is_zero_for_self_drive(): void
    {
        $bookings = app(BookingService::class);

        $this->assertSame('0.00', $bookings->chauffeurFeeFor(RentalType::SelfDrive, 4));
        $this->assertSame('50000.00', $bookings->chauffeurFeeFor(RentalType::Chauffeur, 2));
    }

    public function test_inactive_and_maintenance_vehicles_are_unavailable(): void
    {
        $available = $this->makeVehicle();
        $inactive = $this->makeVehicle(overrides: [
            'plate_number' => 'RAD111X',
            'status' => VehicleStatus::Inactive->value,
        ]);
        $maintenance = $this->makeVehicle(overrides: [
            'plate_number' => 'RAD222Y',
            'status' => VehicleStatus::Maintenance->value,
        ]);

        $availability = app(VehicleAvailabilityService::class);
        $pickup = now()->addDay();
        $return = now()->addDays(3);

        $this->assertTrue($availability->isAvailable($available, $pickup, $return));
        $this->assertFalse($availability->isAvailable($inactive, $pickup, $return));
        $this->assertFalse($availability->isAvailable($maintenance, $pickup, $return));
        $this->assertTrue($availability->categoryIsAvailable($available->category, $pickup, $return, $available->station_id));
    }

    public function test_confirmed_booking_blocks_the_assigned_vehicle_for_overlapping_dates(): void
    {
        $this->seedChartOfAccounts();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(2);
        $return = now()->addDays(5);

        $create = app(BookingService::class)->create([
            'customer_id' => $this->makeCustomer()->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_method' => \App\Enums\PickupMethod::Station->value,
            'return_method' => \App\Enums\ReturnMethod::Station->value,
            'pickup_at' => $pickup->format('Y-m-d H:i'),
            'return_at' => $return->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => \App\Enums\LicenceCategory::B->value,
            'payment_option' => \App\Enums\PaymentOption::PayNow->value,
        ]);

        $this->assertTrue($create['status']);
        $booking = $create['data'];
        $confirm = app(BookingService::class)->confirm($booking);
        $this->assertTrue($confirm['status']);

        $availability = app(VehicleAvailabilityService::class);
        $this->assertFalse($availability->isAvailable($vehicle, $pickup->copy()->addDay(), $return->copy()->subDay()));
        $this->assertTrue($availability->isAvailable($vehicle, $return->copy()->addDay(), $return->copy()->addDays(3)));
        $this->assertNotEmpty($availability->blockingRanges($vehicle));
    }

    public function test_overlapping_booking_is_rejected_for_other_and_same_customer(): void
    {
        $this->seedChartOfAccounts();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(3)->startOfHour();
        $return = now()->addDays(6)->startOfHour();

        $payload = [
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_method' => \App\Enums\PickupMethod::Station->value,
            'return_method' => \App\Enums\ReturnMethod::Station->value,
            'pickup_at' => $pickup->format('Y-m-d H:i'),
            'return_at' => $return->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => \App\Enums\LicenceCategory::B->value,
            'payment_option' => \App\Enums\PaymentOption::PayNow->value,
        ];

        $firstCustomer = $this->makeCustomer(['email' => 'first@example.com']);
        $first = app(BookingService::class)->create([
            ...$payload,
            'customer_id' => $firstCustomer->id,
        ]);
        $this->assertTrue($first['status']);

        $otherCustomer = $this->makeCustomer(['email' => 'other@example.com']);
        $otherAttempt = app(BookingService::class)->create([
            ...$payload,
            'customer_id' => $otherCustomer->id,
            'pickup_at' => $pickup->copy()->addDay()->format('Y-m-d H:i'),
            'return_at' => $return->copy()->subDay()->format('Y-m-d H:i'),
        ]);
        $this->assertFalse($otherAttempt['status']);
        $this->assertSame(__('body.vehicle_unavailable_for_dates'), $otherAttempt['message']);

        $sameCustomerAttempt = app(BookingService::class)->create([
            ...$payload,
            'customer_id' => $firstCustomer->id,
        ]);
        $this->assertFalse($sameCustomerAttempt['status']);
        $this->assertSame(__('body.vehicle_already_booked_by_you'), $sameCustomerAttempt['message']);

        $afterWindow = app(BookingService::class)->create([
            ...$payload,
            'customer_id' => $otherCustomer->id,
            'pickup_at' => $return->copy()->addDay()->format('Y-m-d H:i'),
            'return_at' => $return->copy()->addDays(3)->format('Y-m-d H:i'),
        ]);
        $this->assertTrue($afterWindow['status']);
    }
}