<?php

namespace App\Http\Controllers\Web\Chauffeur;

use App\Enums\RentalType;
use App\Http\Controllers\Controller;
use App\Models\Booking\Booking;
use App\Services\Chauffeur\ChauffeurJobService;
use App\Libraries\ListExportLibrary;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ChauffeurJobController extends Controller
{
    public function __construct(protected ChauffeurJobService $chauffeurJobs)
    {
    }

    public function index(): View
    {
        return view('app.chauffeur.jobs', [
            'bookings' => $this->chauffeurJobs->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->chauffeurJobs->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->chauffeurJobs->exportList());
    }

    public function show(Booking $booking): View
    {
        abort_unless($booking->rental_type === RentalType::Chauffeur, 404);

        $booking->load(['customer', 'corporateCustomer', 'pickupStation', 'returnStation', 'category', 'assignedVehicle', 'assignedDriver']);

        return view('app.chauffeur.show', [
            'booking' => $booking,
        ]);
    }
}
