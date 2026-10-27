<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentOption;
use App\Enums\RentalType;
use App\Models\Booking\Booking;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_staff_dashboard_shows_quick_actions_and_grouped_kpis(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('body.quick_actions'), false)
            ->assertSee(__('body.add_booking'), false)
            ->assertSee(__('body.upcoming_pickups'), false)
            ->assertSee(__('body.operations'), false)
            ->assertSee(__('body.active_bookings'), false)
            ->assertSeeText(__('body.fleet_and_finance'))
            ->assertSee(__('body.bookings_this_week'), false)
            ->assertSee(__('body.recent_bookings'), false);
    }

    public function test_staff_dashboard_lists_overdue_returns_when_present(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle();

        $booking = new Booking([
            'customer_id' => $customer->id,
            'pickup_station_id' => $vehicle->station_id,
            'return_station_id' => $vehicle->station_id,
            'pickup_at' => now()->subDays(5),
            'return_at' => now()->subDay(),
            'rental_days' => 4,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'category_daily_price' => $vehicle->dailyPrice(),
            'extras_total' => 0,
            'chauffeur_fee' => 0,
            'rental_type' => RentalType::SelfDrive->value,
            'payment_option' => PaymentOption::PayAtPickup->value,
            'total_amount' => 200000,
            'assigned_vehicle_id' => $vehicle->id,
        ]);
        $booking->booking_number = 'BK-TEST-OVERDUE';
        $booking->status = BookingStatus::ActiveRental;
        $booking->save();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('body.overdue_returns'), false)
            ->assertSee($customer->name, false);
    }

    public function test_staff_dashboard_lists_document_expiry_alerts(): void
    {
        $this->actingAsStaff();

        $driver = new \App\Models\Driver\Driver([
            'first_name' => 'Alice',
            'last_name' => 'License',
            'phone' => '+250788000111',
            'national_id' => '1199000012345678',
            'license_number' => 'DL-EXP-1',
            'license_expiry' => now()->addDays(10)->toDateString(),
        ]);
        $driver->save();

        $vehicle = $this->makeVehicle();
        $vehicle->insurance_expires_at = now()->subDays(2)->toDateString();
        $vehicle->inspection_expires_at = now()->addDays(20)->toDateString();
        $vehicle->save();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('body.document_expiry_alerts'), false)
            ->assertSee($driver->fullName(), false)
            ->assertSee($vehicle->plate_number, false)
            ->assertSee(__('body.insurance'), false);
    }
}
