<?php

namespace App\Services\Availability;

use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Models\Booking\Booking;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Determines whether a vehicle can be rented for a given date range.
 *
 * A vehicle is available when it is active (not Inactive), has no open
 * maintenance (Scheduled/InProgress) landing inside the requested dates,
 * and has no overlapping booking in a status that occupies it.
 */
class VehicleAvailabilityService
{
    private const BLOCKING_MAINTENANCE_STATUSES = [
        MaintenanceStatus::Scheduled->value,
        MaintenanceStatus::InProgress->value,
    ];

    /**
     * Bookings in these statuses occupy the vehicle for their date range.
     * Cancelled/NoShow/Completed/Draft do not block other bookings.
     */
    private const BLOCKING_BOOKING_STATUSES = [
        BookingStatus::Pending->value,
        BookingStatus::Confirmed->value,
        BookingStatus::ReadyForPickup->value,
        BookingStatus::ActiveRental->value,
    ];

    public function isAvailable(
        Vehicle $vehicle,
        Carbon $pickupAt,
        Carbon $returnAt,
        ?int $exceptBookingId = null,
    ): bool {
        if (in_array($vehicle->status, [VehicleStatus::Inactive, VehicleStatus::Maintenance], true)) {
            return false;
        }

        $hasBlockingMaintenance = $vehicle->maintenances()
            ->whereIn('status', self::BLOCKING_MAINTENANCE_STATUSES)
            ->whereBetween('date', [$pickupAt->toDateString(), $returnAt->toDateString()])
            ->exists();

        if ($hasBlockingMaintenance) {
            return false;
        }

        return $this->blockingBooking($vehicle, $pickupAt, $returnAt, $exceptBookingId) === null;
    }

    /**
     * First active booking that overlaps the requested window on this vehicle.
     * Used to block other customers and stop the same customer rebooking
     * the same car for the same (or overlapping) times.
     */
    public function blockingBooking(
        Vehicle $vehicle,
        Carbon $pickupAt,
        Carbon $returnAt,
        ?int $exceptBookingId = null,
    ): ?Booking {
        $query = $vehicle->bookings()
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->where('pickup_at', '<', $returnAt)
            ->where('return_at', '>', $pickupAt)
            ->orderBy('pickup_at');

        if ($exceptBookingId) {
            $query->where('id', '!=', $exceptBookingId);
        }

        return $query->first();
    }

    /**
     * Message when a vehicle cannot be booked for the requested window.
     *
     * @param  bool  $forStaff  Staff portal should not say "you already have a booking".
     */
    public function unavailableMessage(
        Vehicle $vehicle,
        Carbon $pickupAt,
        Carbon $returnAt,
        ?int $customerId = null,
        ?int $exceptBookingId = null,
        bool $forStaff = false,
    ): string {
        $blocking = $this->blockingBooking($vehicle, $pickupAt, $returnAt, $exceptBookingId);

        if ($blocking && $customerId && (int) $blocking->customer_id === (int) $customerId) {
            return $forStaff
                ? __('body.vehicle_already_booked_by_customer')
                : __('body.vehicle_already_booked_by_you');
        }

        return __('body.vehicle_unavailable_for_dates');
    }

    /**
     * Active booking windows that occupy this vehicle (for calendars / UI hints).
     *
     * @return list<array{start: string, end: string}>
     */
    public function blockingRanges(Vehicle $vehicle): array
    {
        return $vehicle->bookings()
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->where('return_at', '>', now(config('app.timezone')))
            ->orderBy('pickup_at')
            ->get(['pickup_at', 'return_at'])
            ->map(fn ($booking) => [
                'start' => $booking->pickup_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
                'end' => $booking->return_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function applyAvailability(Builder $query, Carbon $pickupAt, Carbon $returnAt, ?int $exceptBookingId = null): Builder
    {
        return $query
            ->whereNotIn('vehicles.status', [
                VehicleStatus::Inactive->value,
                VehicleStatus::Maintenance->value,
            ])
            ->whereDoesntHave('maintenances', function ($q) use ($pickupAt, $returnAt) {
                $q->whereIn('status', self::BLOCKING_MAINTENANCE_STATUSES)
                    ->whereBetween('date', [$pickupAt->toDateString(), $returnAt->toDateString()]);
            })
            ->whereDoesntHave('bookings', function ($q) use ($pickupAt, $returnAt, $exceptBookingId) {
                $q->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
                    ->where('pickup_at', '<', $returnAt)
                    ->where('return_at', '>', $pickupAt);

                if ($exceptBookingId) {
                    $q->where('bookings.id', '!=', $exceptBookingId);
                }
            });
    }

    public function categoryIsAvailable(
        VehicleCategory $category,
        Carbon $pickupAt,
        Carbon $returnAt,
        ?int $stationId = null,
        ?int $exceptBookingId = null,
    ): bool {
        $query = Vehicle::query()->where('vehicle_category_id', $category->id);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        return $this->applyAvailability($query, $pickupAt, $returnAt, $exceptBookingId)->exists();
    }

    /**
     * Daily rate for a category booking before a specific vehicle is assigned:
     * lowest rate among vehicles that are actually free for the window (what customers see on the site).
     */
    public function quotedDailyPriceForCategory(
        VehicleCategory $category,
        Carbon $pickupAt,
        Carbon $returnAt,
        ?int $stationId = null,
        ?int $exceptBookingId = null,
    ): ?string {
        $query = Vehicle::query()->where('vehicle_category_id', $category->id);

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        $vehicles = $this->applyAvailability($query, $pickupAt, $returnAt, $exceptBookingId)->get();

        $amounts = $vehicles
            ->map(fn (Vehicle $vehicle) => (float) ($vehicle->dailyPrice() ?? 0))
            ->filter(fn (float $amount) => $amount > 0);

        if ($amounts->isEmpty()) {
            return null;
        }

        return (string) $amounts->min();
    }
}
