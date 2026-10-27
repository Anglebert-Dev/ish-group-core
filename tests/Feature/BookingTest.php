<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\LicenceCategory;
use App\Enums\PaymentOption;
use App\Enums\RentalType;
use App\Models\Booking\Booking;
use App\Models\Extra\Extra;
use Tests\TestCase;

class BookingTest extends TestCase
{
    public function test_staff_can_create_a_self_drive_booking_with_optional_extras(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();

        $extra = new Extra([
            'name' => 'GPS',
            'price' => '5000.00',
            'type' => ExtraType::Optional->value,
            'status' => ExtraStatus::Active->value,
        ]);
        $extra->save();

        $pickup = now()->addDays(2)->format('Y-m-d H:i:s');
        $return = now()->addDays(5)->format('Y-m-d H:i:s');

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
            'extras' => [$extra->id => 2],
        ])->assertRedirect();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(3, $booking->rental_days);
        $this->assertSame('10000.00', (string) $booking->extras_total);
        $this->assertSame('0.00', (string) $booking->chauffeur_fee);
        $this->assertSame('160000.00', (string) $booking->total_amount);
        $this->assertDatabaseHas('booking_extras', [
            'booking_id' => $booking->id,
            'extra_id' => $extra->id,
            'quantity' => 2,
        ]);
    }

    public function test_chauffeur_booking_adds_the_daily_chauffeur_fee(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();

        $pickup = now()->addDays(2)->format('Y-m-d H:i:s');
        $return = now()->addDays(5)->format('Y-m-d H:i:s');

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::Chauffeur->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ])->assertRedirect();

        $booking = Booking::query()->first();
        $this->assertSame('75000.00', (string) $booking->chauffeur_fee);
        $this->assertSame('225000.00', (string) $booking->total_amount);
    }

    public function test_staff_can_confirm_and_cancel_a_booking(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ]);

        $booking = Booking::query()->first();

        $this->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertDatabaseHas('general_ledgers', [
            'reference' => 'BK-CONF-'.$booking->uuid,
        ]);

        $this->post(route('bookings.cancel', $booking), [
            'reason' => 'Customer changed plans',
        ])->assertRedirect();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_booking_fails_when_the_category_has_no_available_vehicle(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $station = $this->makeStation();
        $category = $this->makeCategory();

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $station->id,
            'return_station_id' => $station->id,
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'vehicle_category_id' => $category->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_staff_can_export_bookings_as_pdf_and_excel(): void
    {
        $this->actingAsStaff();

        $this->get(route('bookings.index'))
            ->assertOk()
            ->assertSee(__('body.pdf'), false)
            ->assertSee(__('body.excel'), false);

        $this->get(route('bookings.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('bookings.export.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_staff_availability_check_endpoint_reports_category_conflicts(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(2)->startOfHour();
        $return = now()->addDays(5)->startOfHour();

        $created = app(\App\Services\Booking\BookingService::class)->create([
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => $pickup->format('Y-m-d H:i'),
            'return_at' => $return->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ]);
        $this->assertTrue($created['status']);

        $this->postJson(route('bookings.check-availability'), [
            'pickup_station_id' => $vehicle->station_id,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'pickup_at' => $pickup->copy()->addDay()->format('Y-m-d H:i'),
            'return_at' => $return->copy()->subDay()->format('Y-m-d H:i'),
        ])->assertOk()->assertJson([
            'available' => false,
            'message' => __('body.no_category_availability'),
        ]);

        $this->postJson(route('bookings.check-availability'), [
            'pickup_station_id' => $vehicle->station_id,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'pickup_at' => $return->copy()->addDay()->format('Y-m-d H:i'),
            'return_at' => $return->copy()->addDays(3)->format('Y-m-d H:i'),
        ])->assertOk()->assertJson([
            'available' => true,
        ]);
    }

    public function test_staff_sees_customer_overlap_message_when_creating(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(3)->startOfHour();
        $return = now()->addDays(6)->startOfHour();

        $payload = [
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => $pickup->format('Y-m-d H:i'),
            'return_at' => $return->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ];

        $first = app(\App\Services\Booking\BookingService::class)->create($payload);
        $this->assertTrue($first['status']);

        $second = app(\App\Services\Booking\BookingService::class)->create($payload);
        $this->assertFalse($second['status']);
        $this->assertSame(__('body.vehicle_already_booked_by_customer'), $second['message']);
    }

    public function test_staff_can_extend_a_confirmed_booking(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(2)->startOfHour();
        $return = now()->addDays(4)->startOfHour();

        $created = app(\App\Services\Booking\BookingService::class)->create([
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => $pickup->format('Y-m-d H:i'),
            'return_at' => $return->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ]);
        $this->assertTrue($created['status']);

        /** @var Booking $booking */
        $booking = $created['data'];
        $this->post(route('bookings.confirm', $booking))->assertRedirect();

        $newReturn = $return->copy()->addDays(2)->format('Y-m-d H:i');
        $this->post(route('bookings.extend', $booking), [
            'new_return_at' => $newReturn,
        ])->assertRedirect()->assertSessionHas('success');

        $booking->refresh();
        $this->assertTrue($booking->return_at->equalTo(\App\Libraries\GeneralLibrary::parseAppDateTime($newReturn)));
        $this->assertGreaterThan(0, (float) $booking->outstanding_balance);
    }

    public function test_assign_vehicle_list_is_filtered_by_pickup_station(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $stationA = $this->makeStation(['code' => 'STA']);
        $stationB = $this->makeStation(['code' => 'STB', 'name' => 'Station B']);
        $category = $this->makeCategory();
        $vehicleA = $this->makeVehicle($category, $stationA, ['plate_number' => 'RAD 101 A']);
        $vehicleB = $this->makeVehicle($category, $stationB, ['plate_number' => 'RAD 202 B']);

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $stationA->id,
            'return_station_id' => $stationA->id,
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'vehicle_category_id' => $category->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
        ]);

        $booking = Booking::query()->first();
        $this->post(route('bookings.confirm', $booking));

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee($vehicleA->plate_number)
            ->assertDontSee($vehicleB->plate_number);
    }

    public function test_corporate_booking_defaults_to_pay_later_and_creates_invoice_on_confirm(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $vehicle = $this->makeVehicle();

        $corporate = new \App\Models\CorporateCustomer\CorporateCustomer([
            'company_name' => 'Acme Logistics',
            'email' => 'fleet@acme.test',
            'phone' => '+250788555666',
            'payment_terms' => 'Net 30',
        ]);
        $corporate->save();

        $this->post(route('bookings.store'), [
            'corporate_customer_id' => $corporate->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::Chauffeur->value,
            'payment_option' => PaymentOption::CorporateBilling->value,
        ])->assertRedirect();

        $booking = Booking::query()->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertTrue($booking->isCorporate());
        $this->assertSame(PaymentOption::CorporateBilling, $booking->payment_option);
        $this->assertTrue($booking->isPayLater());

        $this->post(route('bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(1, $booking->invoices()->count());
        $this->assertSame((string) $booking->total_amount, (string) $booking->invoices()->first()->total);
    }

    public function test_corporate_billing_requires_corporate_customer(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();

        $this->post(route('bookings.store'), [
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW123456',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::CorporateBilling->value,
        ])->assertSessionHasErrors('payment_option');
    }
}
