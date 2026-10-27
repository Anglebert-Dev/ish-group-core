<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\LicenceCategory;
use App\Enums\PaymentOption;
use App\Enums\PickupMethod;
use App\Enums\RentalType;
use App\Enums\ReturnMethod;
use App\Enums\SimulatedPaymentGateway;
use App\Models\Booking\Booking;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    public function test_customer_can_complete_the_self_service_booking_wizard(): void
    {
        $this->seedChartOfAccounts();
        $staff = $this->makeStaff(['email' => 'ops@example.com']);
        $customer = $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();

        $pickup = now()->addDays(3)->format('Y-m-d H:i');
        $return = now()->addDays(6)->format('Y-m-d H:i');

        $this->get(route('booking.details', [
            'vehicle' => $vehicle->uuid,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'rental_type' => RentalType::SelfDrive->value,
        ]))->assertOk();

        $this->post(route('booking.details.store'), [
            'vehicle_id' => $vehicle->id,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'pickup_method' => PickupMethod::Station->value,
            'pickup_station_id' => $vehicle->station_id,
            'return_method' => ReturnMethod::Station->value,
            'return_station_id' => $vehicle->station_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ])->assertRedirect(route('booking.review'));

        $this->get(route('booking.review'))->assertOk()
            ->assertSee(__('body.pay_with_card'))
            ->assertSee(__('body.pay_with_momo'))
            ->assertSee(__('body.total_due_today'));

        $this->post(route('booking.review.confirm'), [
            'payment_gateway' => SimulatedPaymentGateway::Card->value,
        ])->assertRedirect();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentOption::PayNow, $booking->payment_option);
        $this->assertSame(PickupMethod::Station, $booking->pickup_method);
        $this->assertSame($vehicle->station_id, $booking->pickup_station_id);
        $this->assertSame($vehicle->station_id, $booking->return_station_id);
        $this->assertSame($vehicle->id, $booking->assigned_vehicle_id);
        $this->assertSame('150000.00', (string) $booking->total_amount);
        $this->assertStringContainsString(__('body.pay_with_card'), (string) $booking->notes);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type' => 'booking',
            'title' => __('body.notification_customer_booking_title', ['number' => $booking->booking_number]),
            'action_url' => route('bookings.show', $booking, false),
            'status' => 'unread',
        ]);

        $this->get(route('booking.confirmation', $booking))->assertOk()
            ->assertSee(__('body.payment_success_title'))
            ->assertSee(__('body.download_pdf'))
            ->assertSee(__('body.go_to_booking_details'))
            ->assertSee(__('body.booking_paid_confirmed_note'));

        $this->get(route('booking.receipt', $booking))
            ->assertOk()
            ->assertSee(__('body.booking_invoice'))
            ->assertSee($booking->booking_number);

        $this->get(route('booking.receipt.pdf', $booking))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get(route('account.bookings.show', $booking))->assertOk()
            ->assertSee(__('body.pickup_method'))
            ->assertSee(__('body.return_method'))
            ->assertSee(__('body.payment_option'))
            ->assertSee(__('body.payment_summary'))
            ->assertSee(__('body.license_number'))
            ->assertSee(__('body.view_receipt'))
            ->assertSee(__('body.download_invoice'));
    }

    public function test_customer_can_book_with_delivery_and_collection(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();

        $this->post(route('booking.details.store'), [
            'vehicle_id' => $vehicle->id,
            'pickup_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'return_at' => now()->addDays(6)->format('Y-m-d H:i'),
            'pickup_method' => PickupMethod::Delivery->value,
            'return_method' => ReturnMethod::Collection->value,
            'return_station_id' => $vehicle->station_id,
            'delivery_address' => 'KN 5 Ave, Kigali',
            'collection_address' => 'KG 12 Ave, Remera',
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ])->assertRedirect(route('booking.review'));

        $this->post(route('booking.review.confirm'), [
            'payment_gateway' => SimulatedPaymentGateway::MoMo->value,
        ])->assertRedirect();

        $booking = Booking::query()->first();
        $this->assertSame(PickupMethod::Delivery, $booking->pickup_method);
        $this->assertSame(ReturnMethod::Collection, $booking->return_method);
        $this->assertSame('KN 5 Ave, Kigali', $booking->delivery_address);
        $this->assertSame('KG 12 Ave, Remera', $booking->collection_address);
        $this->assertSame($vehicle->station_id, $booking->pickup_station_id);
        $this->assertSame($vehicle->station_id, $booking->return_station_id);
        $this->assertGreaterThan(0, (float) $booking->delivery_fee);
        $this->assertGreaterThan(0, (float) $booking->collection_fee);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_customer_cannot_view_another_customers_booking(): void
    {
        $this->seedChartOfAccounts();
        $owner = $this->makeCustomer(['email' => 'owner-booking@example.com']);
        $this->actingAsCustomer($owner);
        $vehicle = $this->makeVehicle();

        $this->post(route('booking.details.store'), [
            'vehicle_id' => $vehicle->id,
            'pickup_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'return_at' => now()->addDays(6)->format('Y-m-d H:i'),
            'pickup_method' => PickupMethod::Station->value,
            'pickup_station_id' => $vehicle->station_id,
            'return_method' => ReturnMethod::Station->value,
            'return_station_id' => $vehicle->station_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ]);
        $this->post(route('booking.review.confirm'), [
            'payment_gateway' => SimulatedPaymentGateway::Card->value,
        ]);

        $booking = Booking::query()->first();

        $other = $this->makeCustomer(['email' => 'other@example.com']);
        $this->actingAsCustomer($other);

        $this->get(route('account.bookings.show', $booking))->assertNotFound();
    }

    public function test_customer_cannot_book_vehicle_on_already_confirmed_dates(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();
        $pickup = now()->addDays(3)->format('Y-m-d H:i');
        $return = now()->addDays(6)->format('Y-m-d H:i');

        $first = app(\App\Services\Booking\BookingService::class)->create([
            'customer_id' => $this->makeCustomer(['email' => 'first@example.com'])->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_method' => PickupMethod::Station->value,
            'return_method' => ReturnMethod::Station->value,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW111111',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ]);
        $this->assertTrue($first['status']);
        app(\App\Services\Booking\BookingService::class)->confirm($first['data']);

        $this->post(route('booking.details.store'), [
            'vehicle_id' => $vehicle->id,
            'pickup_at' => $pickup,
            'return_at' => $return,
            'pickup_method' => PickupMethod::Station->value,
            'pickup_station_id' => $vehicle->station_id,
            'return_method' => ReturnMethod::Station->value,
            'return_station_id' => $vehicle->station_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ])->assertRedirect(route('vehicles.show', $vehicle))
            ->assertSessionHas('error', __('body.vehicle_unavailable_for_dates'));

        $this->get(route('vehicles.show', [
            'vehicle' => $vehicle,
            'pickup_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'return_at' => now()->addDays(6)->format('Y-m-d\TH:i'),
        ]))->assertOk()
            ->assertSee(__('body.unavailable'));
    }

    public function test_booking_details_validation_returns_to_wizard_with_errors(): void
    {
        $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();

        $response = $this->from(route('booking.details', ['vehicle' => $vehicle->uuid]))
            ->post(route('booking.details.store'), [
                'vehicle_id' => $vehicle->id,
                'pickup_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'return_at' => now()->addDays(4)->format('Y-m-d H:i'),
                'pickup_method' => PickupMethod::Station->value,
                'pickup_station_id' => $vehicle->station_id,
                'return_method' => ReturnMethod::Station->value,
                'return_station_id' => $vehicle->station_id,
                'rental_type' => RentalType::SelfDrive->value,
                'payment_option' => PaymentOption::PayNow->value,
            ]);

        $response->assertRedirect(route('booking.details', ['vehicle' => $vehicle->uuid]))
            ->assertSessionHasErrors(['driver_license_number', 'driver_license_category']);

        $this->followingRedirects()
            ->from(route('booking.details', ['vehicle' => $vehicle->uuid]))
            ->post(route('booking.details.store'), [
                'vehicle_id' => $vehicle->id,
                'pickup_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'return_at' => now()->addDays(4)->format('Y-m-d H:i'),
                'pickup_method' => PickupMethod::Station->value,
                'pickup_station_id' => $vehicle->station_id,
                'return_method' => ReturnMethod::Station->value,
                'return_station_id' => $vehicle->station_id,
                'rental_type' => RentalType::SelfDrive->value,
                'payment_option' => PaymentOption::PayNow->value,
            ])
            ->assertOk()
            ->assertSee(__('body.license_number_required_self_drive'))
            ->assertSee(__('body.license_category_required_self_drive'));
    }

    public function test_pickup_within_1_hour_is_rejected(): void
    {
        $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();

        $this->post(route('booking.details.store'), [
            'vehicle_id' => $vehicle->id,
            'pickup_at' => now()->addMinutes(30)->format('Y-m-d H:i'),
            'return_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'pickup_method' => PickupMethod::Station->value,
            'pickup_station_id' => $vehicle->station_id,
            'return_method' => ReturnMethod::Station->value,
            'return_station_id' => $vehicle->station_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ])->assertSessionHasErrors('pickup_at');
    }

    public function test_guest_returns_to_booking_details_after_login(): void
    {
        $vehicle = $this->makeVehicle();
        $this->makeCustomer(['email' => 'booker@example.com']);

        $bookingDetails = route('booking.details', [
            'vehicle' => $vehicle->uuid,
            'pickup_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'return_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'rental_type' => RentalType::SelfDrive->value,
        ]);

        $this->get($bookingDetails)->assertRedirect(route('login'));

        $response = $this->post(route('login.store'), [
            'login' => 'booker@example.com',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/booking/details', (string) $location);
        $this->assertStringContainsString($vehicle->uuid, (string) $location);
    }

    public function test_guest_returns_to_booking_details_after_register(): void
    {
        $vehicle = $this->makeVehicle();

        $bookingDetails = route('booking.details', [
            'vehicle' => $vehicle->uuid,
            'pickup_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'return_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'rental_type' => RentalType::SelfDrive->value,
        ]);

        $this->get($bookingDetails)->assertRedirect(route('login'));

        $response = $this->post(route('register.store'), [
            'account_type' => 'personal',
            'name' => 'New Booker',
            'email' => 'new-booker@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/booking/details', (string) $location);
        $this->assertStringContainsString($vehicle->uuid, (string) $location);
    }

    public function test_customer_can_continue_payment_or_cancel_a_pending_booking(): void
    {
        $this->seedChartOfAccounts();
        $customer = $this->actingAsCustomer();
        $vehicle = $this->makeVehicle();

        $result = app(\App\Services\Booking\BookingService::class)->create([
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_method' => PickupMethod::Station->value,
            'return_method' => ReturnMethod::Station->value,
            'pickup_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'return_at' => now()->addDays(6)->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ]);

        $this->assertTrue($result['status']);
        /** @var Booking $booking */
        $booking = $result['data'];
        $this->assertSame(BookingStatus::Pending, $booking->status);

        $this->get(route('account.bookings.show', $booking))->assertOk()
            ->assertSee(__('body.continue_to_payment'))
            ->assertSee(__('body.cancel_booking'))
            ->assertSee(__('body.pending_booking_action_title'));

        $this->get(route('account.bookings.pay', $booking))->assertOk()
            ->assertSee(__('body.pay_with_card'))
            ->assertSee(__('body.total_due_today'));

        $this->post(route('account.bookings.pay.confirm', $booking), [
            'payment_gateway' => SimulatedPaymentGateway::MoMo->value,
        ])->assertRedirect(route('booking.confirmation', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        $pending = app(\App\Services\Booking\BookingService::class)->create([
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_method' => PickupMethod::Station->value,
            'return_method' => ReturnMethod::Station->value,
            'pickup_at' => now()->addDays(10)->format('Y-m-d H:i'),
            'return_at' => now()->addDays(12)->format('Y-m-d H:i'),
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'rental_type' => RentalType::SelfDrive->value,
            'driver_license_number' => 'RW998877',
            'driver_license_category' => LicenceCategory::B->value,
            'payment_option' => PaymentOption::PayNow->value,
        ])['data'];

        $this->post(route('account.bookings.cancel', $pending), [
            'reason' => 'Changed plans',
        ])->assertRedirect(route('account.bookings.show', $pending));

        $pending->refresh();
        $this->assertSame(BookingStatus::Cancelled, $pending->status);
        $this->assertSame('Changed plans', $pending->cancellation_reason);
    }
}
