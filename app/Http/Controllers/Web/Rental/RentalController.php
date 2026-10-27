<?php

namespace App\Http\Controllers\Web\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\RecordPickupRequest;
use App\Http\Requests\Rental\RecordReturnRequest;
use App\Models\Booking\Booking;
use App\Services\Rental\RentalService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RentalController extends Controller
{
    public function __construct(protected RentalService $rentals)
    {
    }

    public function index(): View
    {
        return view('app.rentals.index', [
            'bookings' => $this->rentals->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->rentals->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->rentals->exportList());
    }

    public function show(Booking $booking): View
    {
        $booking->load(['customer', 'corporateCustomer', 'category', 'assignedVehicle', 'assignedDriver', 'pickupInspection.photos', 'returnInspection.photos']);

        return view('app.rentals.show', [
            'booking' => $booking,
        ]);
    }

    public function recordPickup(RecordPickupRequest $request, Booking $booking): RedirectResponse
    {
        $result = $this->rentals->recordPickup($booking, $request->validated(), $request->file('photos', []));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function recordReturn(RecordReturnRequest $request, Booking $booking): RedirectResponse
    {
        $result = $this->rentals->recordReturn($booking, $request->validated(), $request->file('photos', []));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
