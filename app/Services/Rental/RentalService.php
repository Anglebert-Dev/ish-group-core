<?php

namespace App\Services\Rental;

use App\Enums\BookingStatus;
use App\Enums\InspectionType;
use App\Enums\VehicleStatus;
use App\Libraries\UploadLibrary;
use App\Models\Booking\Booking;
use App\Models\Inspection\Inspection;
use App\Models\Inspection\InspectionPhoto;
use App\Models\Vehicle\Vehicle;
use App\Enums\PaymentOption;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Repositories\Booking\BookingRepository;
use App\Repositories\Inspection\InspectionRepository;
use App\Services\BaseService;
use App\Services\GeneralLedger\BookingLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The physical rental operation: pickup hands the vehicle over (Ready for
 * Pickup -> Active Rental, vehicle -> OnHire) and return brings it back
 * (Active Rental -> Completed, vehicle -> Available), settling the final
 * charges. Kept separate from BookingService because this is a distinct
 * responsibility — the booking is the reservation/agreement, this is the
 * vehicle actually leaving and returning to the station.
 */
class RentalService extends BaseService
{
    public function __construct(
        protected BookingRepository $bookings,
        protected InspectionRepository $inspections,
        protected BookingLedgerService $bookingLedger,
    ) {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'assignedVehicle', 'assignedDriver'])
            ->whereIn('status', [BookingStatus::ReadyForPickup->value, BookingStatus::ActiveRental->value])
            ->latest()
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'assignedVehicle'])
            ->whereIn('status', [BookingStatus::ReadyForPickup->value, BookingStatus::ActiveRental->value])
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => [
                $booking->booking_number,
                $booking->customerName(),
                $booking->assignedVehicle
                    ? $booking->assignedVehicle->name().' — '.$booking->assignedVehicle->plate_number
                    : '—',
                GeneralLibrary::formatDateTime($booking->pickup_at),
                GeneralLibrary::formatDateTime($booking->return_at),
                $booking->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.rentals'), 'rentals', [
            __('body.booking_number'),
            __('body.customer'),
            __('body.vehicle'),
            __('body.pickup_datetime'),
            __('body.return_datetime'),
            __('body.status'),
        ], $rows);
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $photos
     */
    public function recordPickup(Booking $booking, array $data, array $photos = []): array
    {
        if ($booking->status !== BookingStatus::ReadyForPickup) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        $vehicle = $booking->assignedVehicle;

        if (! $vehicle) {
            return $this->failure(__('body.assign_vehicle_first'));
        }

        // The deposit always equals the booking's full calculated total —
        // it isn't a separate, staff-entered security amount, so it can
        // never drift from what was actually charged.
        $inspection = Inspection::create([
            'booking_id' => $booking->id,
            'type' => InspectionType::Pickup,
            'mileage' => $data['mileage'],
            'fuel_level' => $data['fuel_level'],
            'condition_notes' => $data['condition_notes'] ?? null,
            'deposit_amount' => $booking->total_amount,
        ]);

        $this->storePhotos($inspection, $photos);

        if ($data['mileage'] > ($vehicle->mileage ?? 0)) {
            $vehicle->mileage = $data['mileage'];
        }
        $vehicle->status = VehicleStatus::OnHire;
        $vehicle->save();

        $booking->status = BookingStatus::ActiveRental;
        $this->bookings->save($booking);

        if ($booking->payment_option === PaymentOption::PayAtPickup) {
            $this->bookingLedger->postPayment($booking, (string) $booking->total_amount, 'pickup');
        }

        return $this->success($booking, __('body.pickup_recorded'));
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $photos
     */
    public function recordReturn(Booking $booking, array $data, array $photos = []): array
    {
        if ($booking->status !== BookingStatus::ActiveRental) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        $vehicle = $booking->assignedVehicle;

        if (! $vehicle) {
            return $this->failure(__('body.assign_vehicle_first'));
        }

        $booking->loadMissing('pickupInspection');
        $pickupMileage = (int) ($booking->pickupInspection?->mileage ?? 0);
        $returnMileage = (int) $data['mileage'];

        if ($returnMileage < $pickupMileage) {
            return $this->failure(__('body.return_mileage_must_be_at_least_pickup', [
                'mileage' => number_format($pickupMileage),
            ]));
        }

        $damageCharges = $data['damage_charges'] ?? 0;

        $inspection = Inspection::create([
            'booking_id' => $booking->id,
            'type' => InspectionType::Return,
            'mileage' => $returnMileage,
            'fuel_level' => $data['fuel_level'],
            'condition_notes' => $data['condition_notes'] ?? null,
            'damage_charges' => $damageCharges,
        ]);

        $this->storePhotos($inspection, $photos);

        if ($returnMileage > ($vehicle->mileage ?? 0)) {
            $vehicle->mileage = $returnMileage;
        }

        $actualReturnAt = now();
        $secondsLate = $actualReturnAt->gt($booking->return_at)
            ? $booking->return_at->diffInSeconds($actualReturnAt)
            : 0;
        $extraDays = (int) ceil($secondsLate / 86400);
        $extraDaysCharge = bcmul((string) $booking->category_daily_price, (string) $extraDays, 2);

        // The deposit taken at pickup already covers the full booked total,
        // so only the charges discovered at/after return are still owed.
        $outstandingBalance = bcadd($extraDaysCharge, (string) $damageCharges, 2);

        $booking->actual_return_at = $actualReturnAt;
        $booking->extra_days_charge = $extraDaysCharge;
        $booking->outstanding_balance = $outstandingBalance;
        $booking->status = BookingStatus::Completed;
        $this->bookings->save($booking);

        $vehicle->status = $this->hasOtherOpenRental($vehicle, $booking) ? $vehicle->status : VehicleStatus::Available;
        $vehicle->save();

        $this->bookingLedger->postReturnCharges($booking);

        return $this->success($booking, __('body.return_recorded'));
    }

    protected function hasOtherOpenRental(Vehicle $vehicle, Booking $ignoring): bool
    {
        return $vehicle->bookings()
            ->whereKeyNot($ignoring->id)
            ->whereIn('status', [BookingStatus::ReadyForPickup->value, BookingStatus::ActiveRental->value])
            ->exists();
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $files
     */
    protected function storePhotos(Inspection $inspection, array $files): void
    {
        $nextOrder = 1;

        foreach ($files as $file) {
            $path = UploadLibrary::store($file, 'inspections/'.$inspection->uuid, 'public');

            InspectionPhoto::create([
                'inspection_id' => $inspection->id,
                'file_path' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }
    }
}
