<?php

namespace App\Http\Controllers\Web\Booking;

use App\Enums\BookingStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverStatus;
use App\Enums\RentalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AssignDriverRequest;
use App\Http\Requests\Booking\AssignVehicleRequest;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\CheckBookingAvailabilityRequest;
use App\Http\Requests\Booking\ExtendBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExportLibrary;
use App\Models\Booking\Booking;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use App\Models\Driver\Driver;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use App\Services\Availability\VehicleAvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Extra\ExtraService;
use App\Services\Station\StationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected VehicleAvailabilityService $availability,
        protected ExtraService $extras,
        protected StationService $stations,
    ) {
    }

    public function index(): View
    {
        return view('app.bookings.index', [
            'bookings' => $this->bookings->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->bookings->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->bookings->exportList());
    }

    public function create(): View
    {
        $stationOptions = $this->stations->activeOptions();
        $defaultStation = (int) ($stationOptions->keys()->first() ?? Station::query()->orderBy('name')->value('id') ?? 0);

        return view('app.bookings.create', [
            'customers' => Customer::orderBy('name')->get(),
            'corporateCustomers' => CorporateCustomer::orderBy('company_name')->get(),
            'stations' => $stationOptions,
            'defaultPickupStation' => (int) old('pickup_station_id', $defaultStation),
            'defaultReturnStation' => (int) old('return_station_id', $defaultStation),
            'categories' => VehicleCategory::query()
                ->with('vehicles:id,daily_price,vehicle_category_id')
                ->orderBy('name')
                ->get(),
            'extras' => $this->extras->optionalCatalog(),
            'chauffeurDailyFee' => $this->extras->chauffeurDailyFee(),
            'deliveryFee' => $this->extras->deliveryFee(),
            'collectionFee' => $this->extras->collectionFee(),
            'fallbackStationId' => $defaultStation,
        ]);
    }

    public function checkAvailability(CheckBookingAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $pickupAt = GeneralLibrary::parseAppDateTime($data['pickup_at']);
        $returnAt = GeneralLibrary::parseAppDateTime($data['return_at']);
        $category = VehicleCategory::findOrFail($data['vehicle_category_id']);

        $available = $this->availability->categoryIsAvailable(
            $category,
            $pickupAt,
            $returnAt,
            (int) $data['pickup_station_id'],
        );

        return response()->json([
            'available' => $available,
            'message' => $available
                ? __('body.trip_availability_ok')
                : __('body.no_category_availability'),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $result = $this->bookings->create($request->safe()->except('extras'), $request->input('extras', []));

        if (! $result['status']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('bookings.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['customer', 'corporateCustomer', 'pickupStation', 'returnStation', 'category', 'assignedVehicle', 'assignedDriver', 'extras.extra']);

        $availableVehicles = collect();
        $availableDrivers = collect();

        if ($booking->status === BookingStatus::Confirmed) {
            $vehicleQuery = Vehicle::query()
                ->where('vehicle_category_id', $booking->vehicle_category_id)
                ->where('station_id', $booking->pickup_station_id);

            $availableVehicles = $this->availability->applyAvailability(
                $vehicleQuery,
                $booking->pickup_at,
                $booking->return_at,
                $booking->id,
            )->get();

            if ($booking->rental_type === RentalType::Chauffeur && $booking->assigned_vehicle_id) {
                $availableDrivers = Driver::query()
                    ->where('status', DriverStatus::Active->value)
                    ->where('availability_status', DriverAvailabilityStatus::Available->value)
                    ->get();
            }
        }

        $canExtend = in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::ReadyForPickup,
            BookingStatus::ActiveRental,
        ], true);

        return view('app.bookings.show', [
            'booking' => $booking,
            'availableVehicles' => $availableVehicles,
            'availableDrivers' => $availableDrivers,
            'canExtend' => $canExtend,
            'extensionDailySurcharge' => $this->extras->extensionDailySurcharge(),
        ]);
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        $result = $this->bookings->confirm($booking);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $result = $this->bookings->cancel($booking, $request->validated('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function markNoShow(Booking $booking): RedirectResponse
    {
        $result = $this->bookings->markNoShow($booking);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function assignVehicle(AssignVehicleRequest $request, Booking $booking): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($request->validated('vehicle_id'));
        $result = $this->bookings->assignVehicle($booking, $vehicle);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function assignDriver(AssignDriverRequest $request, Booking $booking): RedirectResponse
    {
        $driver = Driver::findOrFail($request->validated('driver_id'));
        $result = $this->bookings->assignDriver($booking, $driver);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function markReadyForPickup(Booking $booking): RedirectResponse
    {
        $result = $this->bookings->markReadyForPickup($booking);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function extend(ExtendBookingRequest $request, Booking $booking): RedirectResponse
    {
        $result = $this->bookings->extend($booking, $request->validated('new_return_at'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
