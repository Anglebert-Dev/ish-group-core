<?php

namespace App\Services\Chauffeur;

use App\Enums\RentalType;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Models\Booking\Booking;
use App\Repositories\Booking\BookingRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A "chauffeur job" is not its own entity — it's a chauffeur-rental Booking
 * viewed from the driver's-trip angle (who's driving, which vehicle, when).
 * Kept as a thin read-side service over Booking rather than a parallel
 * table, since nothing here needs data Booking doesn't already have.
 */
class ChauffeurJobService extends BaseService
{
    public function __construct(protected BookingRepository $bookings)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'assignedVehicle', 'assignedDriver'])
            ->where('rental_type', RentalType::Chauffeur->value)
            ->latest()
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'assignedVehicle', 'assignedDriver'])
            ->where('rental_type', RentalType::Chauffeur->value)
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => [
                $booking->booking_number,
                $booking->customerName(),
                $booking->assignedDriver?->fullName() ?? __('body.not_assigned_yet'),
                $booking->assignedVehicle
                    ? $booking->assignedVehicle->name().' — '.$booking->assignedVehicle->plate_number
                    : __('body.not_assigned_yet'),
                GeneralLibrary::formatDateTime($booking->pickup_at),
                $booking->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.chauffeur_jobs'), 'chauffeur-jobs', [
            __('body.booking_number'),
            __('body.customer'),
            __('body.assigned_driver'),
            __('body.assigned_vehicle'),
            __('body.pickup_datetime'),
            __('body.status'),
        ], $rows);
    }
}
