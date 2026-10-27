<?php

namespace App\Http\Controllers\Web\Booking;

use App\Enums\BookingStatus;
use App\Enums\PickupMethod;
use App\Enums\RentalType;
use App\Enums\ReturnMethod;
use App\Enums\SimulatedPaymentGateway;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\ConfirmBookingPaymentRequest;
use App\Http\Requests\Booking\ExtendBookingRequest;
use App\Http\Requests\Booking\StoreBookingDetailsRequest;
use App\Models\Booking\Booking;
use App\Models\Vehicle\Vehicle;
use App\Services\Availability\VehicleAvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Extra\ExtraService;
use App\Services\Station\StationService;
use App\Libraries\BookingReceiptLibrary;
use App\Libraries\GeneralLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public self-service booking: vehicle → details → payment (simulated gateway)
 * → confirmation. Pay Now creates the booking and confirms it immediately.
 */
class CustomerBookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected VehicleAvailabilityService $availability,
        protected ExtraService $extras,
        protected StationService $stations,
    ) {
    }

    public function details(Request $request): View|RedirectResponse
    {
        $draft = session('booking_draft');
        $vehicle = Vehicle::where('uuid', $request->query('vehicle'))->first();

        if (! $vehicle && is_array($draft) && ! empty($draft['vehicle_id'])) {
            $vehicle = Vehicle::find($draft['vehicle_id']);
        }

        if (! $vehicle && old('vehicle_id')) {
            $vehicle = Vehicle::find(old('vehicle_id'));
        }

        if (! $vehicle || $vehicle->status !== VehicleStatus::Available) {
            return redirect()->route('vehicles.index')->with('error', __('body.vehicle_not_available'));
        }

        $restoreDraft = is_array($draft)
            && (int) ($draft['vehicle_id'] ?? 0) === (int) $vehicle->id
            && ! $request->has('pickup_at')
            && ! $request->session()->hasOldInput();

        $earliest = GeneralLibrary::earliestPickupLocalValue();
        $pickupAt = (string) ($restoreDraft ? ($draft['pickup_at'] ?? '') : $request->query('pickup_at'));
        $returnAt = (string) ($restoreDraft ? ($draft['return_at'] ?? '') : $request->query('return_at'));

        if ($pickupAt !== '' && $pickupAt < $earliest) {
            $pickupAt = $earliest;
        }

        if ($pickupAt !== '' && $returnAt !== '') {
            try {
                $pickupCarbon = GeneralLibrary::parseAppDateTime($pickupAt);
                $returnCarbon = GeneralLibrary::parseAppDateTime($returnAt);

                if ($pickupCarbon->lt($returnCarbon)
                    && ! $this->availability->isAvailable($vehicle, $pickupCarbon, $returnCarbon)) {
                    return redirect()
                        ->route('vehicles.show', $vehicle)
                        ->withInput()
                        ->with('error', $this->availability->unavailableMessage(
                            $vehicle,
                            $pickupCarbon,
                            $returnCarbon,
                            auth('customer')->id() ? (int) auth('customer')->id() : null,
                        ));
                }
            } catch (\Throwable) {
                // Invalid query dates — let the form validation handle them later.
            }
        }

        $defaultPickupStation = (int) (
            $restoreDraft
                ? ($draft['pickup_station_id'] ?? $vehicle->station_id)
                : ($request->query('station') ?: $vehicle->station_id)
        );
        $defaultReturnStation = (int) (
            $restoreDraft
                ? ($draft['return_station_id'] ?? $defaultPickupStation)
                : ($request->query('return_station') ?: $defaultPickupStation)
        );

        $selectedExtras = $restoreDraft
            ? collect($draft['extras'] ?? [])
                ->mapWithKeys(fn ($qty, $id) => [(int) $id => (int) $qty])
                ->filter(fn ($qty) => $qty > 0)
                ->all()
            : $this->parseRequestedExtras($request);

        return view('public.booking.details', [
            'vehicle' => $vehicle,
            'pickupAt' => $pickupAt,
            'returnAt' => $returnAt,
            'rentalType' => (string) (
                $restoreDraft
                    ? ($draft['rental_type'] ?? RentalType::SelfDrive->value)
                    : $request->query('rental_type', RentalType::SelfDrive->value)
            ),
            'extras' => $this->extras->optionalCatalog(),
            'selectedExtras' => $selectedExtras,
            'stations' => $this->stations->activeOptions(),
            'defaultPickupStation' => $defaultPickupStation,
            'defaultReturnStation' => $defaultReturnStation ?: $defaultPickupStation,
            'pickupMethod' => (string) (
                $restoreDraft
                    ? ($draft['pickup_method'] ?? PickupMethod::Station->value)
                    : $request->query('pickup_method', PickupMethod::Station->value)
            ),
            'returnMethod' => (string) (
                $restoreDraft
                    ? ($draft['return_method'] ?? ReturnMethod::Station->value)
                    : $request->query('return_method', ReturnMethod::Station->value)
            ),
            'deliveryAddress' => $restoreDraft ? (string) ($draft['delivery_address'] ?? '') : '',
            'collectionAddress' => $restoreDraft ? (string) ($draft['collection_address'] ?? '') : '',
            'driverLicenseNumber' => $restoreDraft ? (string) ($draft['driver_license_number'] ?? '') : '',
            'driverLicenseCategory' => $restoreDraft ? (string) ($draft['driver_license_category'] ?? '') : '',
            'deliveryFee' => $this->extras->deliveryFee(),
            'collectionFee' => $this->extras->collectionFee(),
            'chauffeurDailyFee' => $this->extras->chauffeurDailyFee(),
            'wizardStartStep' => $restoreDraft ? 5 : 1,
        ]);
    }

    public function storeDetails(StoreBookingDetailsRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($request->validated('vehicle_id'));
        $data = $request->validated();

        $pickupAt = GeneralLibrary::parseAppDateTime($data['pickup_at']);
        $returnAt = GeneralLibrary::parseAppDateTime($data['return_at']);

        if (! $this->availability->isAvailable($vehicle, $pickupAt, $returnAt)) {
            return redirect()
                ->route('vehicles.show', $vehicle)
                ->withInput()
                ->with('error', $this->availability->unavailableMessage(
                    $vehicle,
                    $pickupAt,
                    $returnAt,
                    auth('customer')->id() ? (int) auth('customer')->id() : null,
                ));
        }

        if ($data['pickup_method'] === PickupMethod::Delivery->value) {
            if (! $vehicle->station_id) {
                return redirect()->route('vehicles.show', $vehicle)->with('error', __('body.no_category_availability'));
            }
            $data['pickup_station_id'] = $vehicle->station_id;
        }

        if ($data['return_method'] === ReturnMethod::Collection->value) {
            if (! $vehicle->station_id) {
                return redirect()->route('vehicles.show', $vehicle)->with('error', __('body.no_category_availability'));
            }
            $data['return_station_id'] = $vehicle->station_id;
        }

        if (! ($data['pickup_station_id'] ?? null) || ! ($data['return_station_id'] ?? null)) {
            return redirect()->route('vehicles.show', $vehicle)->with('error', __('body.no_category_availability'));
        }

        session(['booking_draft' => $data]);

        return redirect()->route('booking.review');
    }

    public function review(): View|RedirectResponse
    {
        $draft = session('booking_draft');

        if (! $draft) {
            return redirect()->route('vehicles.index');
        }

        $vehicle = Vehicle::find($draft['vehicle_id']);

        if (! $vehicle) {
            session()->forget('booking_draft');

            return redirect()->route('vehicles.index');
        }

        $pickupAt = GeneralLibrary::parseAppDateTime($draft['pickup_at']);
        $returnAt = GeneralLibrary::parseAppDateTime($draft['return_at']);
        $rentalDays = max(1, $pickupAt->diffInDays($returnAt));
        $categorySubtotal = bcmul((string) ($vehicle->dailyPrice() ?? 0), (string) $rentalDays, 2);

        $selectedExtras = collect($draft['extras'] ?? [])->filter(fn ($qty) => (int) $qty > 0);
        $extraLines = collect();
        $extrasTotal = '0.00';

        if ($selectedExtras->isNotEmpty()) {
            $catalog = $this->extras->optionalCatalog()->keyBy('id');

            foreach ($selectedExtras as $extraId => $quantity) {
                $extra = $catalog->get((int) $extraId);

                if (! $extra) {
                    continue;
                }

                $lineTotal = bcmul((string) $extra->price, (string) $quantity, 2);
                $extrasTotal = bcadd($extrasTotal, $lineTotal, 2);
                $extraLines->push(['extra' => $extra, 'quantity' => (int) $quantity, 'total' => $lineTotal]);
            }
        }

        $rentalType = RentalType::from((string) $draft['rental_type']);
        $pickupMethod = PickupMethod::from((string) $draft['pickup_method']);
        $returnMethod = ReturnMethod::from((string) ($draft['return_method'] ?? ReturnMethod::Station->value));
        $chauffeurFee = $this->bookings->chauffeurFeeFor($rentalType, $rentalDays);
        $deliveryFee = $this->bookings->deliveryFeeFor($pickupMethod);
        $collectionFee = $this->bookings->collectionFeeFor($returnMethod);
        $totalAmount = bcadd(
            bcadd(bcadd(bcadd($categorySubtotal, $extrasTotal, 2), $chauffeurFee, 2), $deliveryFee, 2),
            $collectionFee,
            2
        );

        $available = $this->availability->isAvailable($vehicle, $pickupAt, $returnAt);

        return view('public.booking.review', [
            'vehicle' => $vehicle,
            'draft' => $draft,
            'pickupAt' => $pickupAt,
            'returnAt' => $returnAt,
            'rentalDays' => $rentalDays,
            'categorySubtotal' => $categorySubtotal,
            'extraLines' => $extraLines,
            'extrasTotal' => $extrasTotal,
            'chauffeurFee' => $chauffeurFee,
            'deliveryFee' => $deliveryFee,
            'collectionFee' => $collectionFee,
            'pickupMethod' => $pickupMethod,
            'returnMethod' => $returnMethod,
            'totalAmount' => $totalAmount,
            'available' => $available,
            'stations' => $this->stations->activeOptions(),
            'paymentGateways' => SimulatedPaymentGateway::cases(),
        ]);
    }

    public function confirm(ConfirmBookingPaymentRequest $request): RedirectResponse
    {
        $draft = session('booking_draft');

        if (! $draft) {
            return redirect()->route('vehicles.index');
        }

        $vehicle = Vehicle::find($draft['vehicle_id']);

        if (! $vehicle) {
            session()->forget('booking_draft');

            return redirect()->route('vehicles.index');
        }

        $gateway = SimulatedPaymentGateway::from($request->validated('payment_gateway'));
        $notes = trim((string) ($draft['notes'] ?? ''));
        $simNote = '[Simulated payment via '.$gateway->label().']';
        $notes = $notes === '' ? $simNote : $notes."\n".$simNote;

        $result = $this->bookings->create([
            'customer_id' => auth('customer')->id(),
            'pickup_station_id' => $draft['pickup_station_id'],
            'return_station_id' => $draft['return_station_id'],
            'pickup_method' => $draft['pickup_method'],
            'return_method' => $draft['return_method'] ?? ReturnMethod::Station->value,
            'delivery_address' => $draft['delivery_address'] ?? null,
            'collection_address' => $draft['collection_address'] ?? null,
            'pickup_at' => $draft['pickup_at'],
            'return_at' => $draft['return_at'],
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'assigned_vehicle_id' => $vehicle->id,
            'rental_type' => $draft['rental_type'],
            'driver_license_number' => $draft['driver_license_number'] ?? null,
            'driver_license_category' => $draft['driver_license_category'] ?? null,
            'payment_option' => $draft['payment_option'],
            'notes' => $notes,
        ], $draft['extras'] ?? []);

        if (! $result['status']) {
            return redirect()->route('booking.review')->with('error', $result['message']);
        }

        /** @var Booking $booking */
        $booking = $result['data'];
        $confirm = $this->bookings->confirm($booking);

        if (! $confirm['status']) {
            return redirect()->route('booking.confirmation', $booking)
                ->with('error', $confirm['message']);
        }

        session()->forget('booking_draft');

        return redirect()
            ->route('booking.confirmation', $booking)
            ->with('success', __('body.payment_simulated_success'))
            ->with('payment_gateway', $gateway->label());
    }

    public function confirmation(Booking $booking): View
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        return view('public.booking.confirmation', [
            'booking' => $booking->load(['extras.extra', 'pickupStation', 'returnStation', 'category', 'assignedVehicle']),
        ]);
    }

    public function receipt(Booking $booking): View
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        $booking->load(['extras.extra', 'pickupStation', 'returnStation', 'category', 'customer', 'assignedVehicle']);

        return view('public.booking.receipt', [
            'booking' => $booking,
            'generatedAt' => GeneralLibrary::formatDateTime(now()),
        ]);
    }

    public function downloadReceipt(Booking $booking): Response
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        return BookingReceiptLibrary::pdf($booking);
    }

    public function myBookings(): View
    {
        $bookings = Booking::query()
            ->where('customer_id', auth('customer')->id())
            ->with(['category', 'pickupStation', 'assignedVehicle'])
            ->latest()
            ->paginate(10);

        return view('public.account.bookings.index', [
            'bookings' => $bookings,
        ]);
    }

    public function myBookingShow(Booking $booking): View
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        $booking->load([
            'category',
            'pickupStation',
            'returnStation',
            'assignedVehicle.images',
            'assignedDriver',
            'extras.extra',
            'pickupInspection',
            'returnInspection',
        ]);

        $canExtend = in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::ReadyForPickup,
            BookingStatus::ActiveRental,
        ], true);

        $galleryVehicle = $booking->assignedVehicle;
        $galleryImages = collect();
        $galleryTitle = $booking->category?->name;
        $galleryCover = $booking->category?->imageUrl();

        if ($galleryVehicle) {
            $galleryImages = $galleryVehicle->images;
            $galleryTitle = $galleryVehicle->name();
            $galleryCover = $galleryVehicle->coverImageUrl() ?: $galleryCover;
        } else {
            $categoryVehicles = Vehicle::query()
                ->with('images')
                ->where('vehicle_category_id', $booking->vehicle_category_id)
                ->whereHas('images')
                ->latest('id')
                ->limit(6)
                ->get();

            $galleryImages = $categoryVehicles
                ->flatMap(fn (Vehicle $vehicle) => $vehicle->images)
                ->unique('id')
                ->values()
                ->take(12);

            if (! $galleryCover && $galleryImages->isNotEmpty()) {
                $galleryCover = $galleryImages->first()->url();
            }
        }

        return view('public.account.bookings.show', [
            'booking' => $booking,
            'canExtend' => $canExtend,
            'canCancel' => in_array($booking->status, [
                BookingStatus::Pending,
                BookingStatus::Confirmed,
                BookingStatus::ReadyForPickup,
            ], true),
            'canContinuePayment' => $booking->status === BookingStatus::Pending,
            'extensionDailySurcharge' => $this->extras->extensionDailySurcharge(),
            'galleryTitle' => $galleryTitle,
            'galleryCover' => $galleryCover,
            'galleryImages' => $galleryImages,
        ]);
    }

    public function pay(Booking $booking): View|RedirectResponse
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        if ($booking->status !== BookingStatus::Pending) {
            return redirect()
                ->route('account.bookings.show', $booking)
                ->with('error', __('body.booking_invalid_transition'));
        }

        $booking->load(['category', 'extras.extra', 'pickupStation', 'returnStation', 'assignedVehicle.category']);

        $vehicle = $booking->assignedVehicle
            ?? Vehicle::query()
                ->with('category')
                ->where('vehicle_category_id', $booking->vehicle_category_id)
                ->first();

        $extraLines = $booking->extras->map(fn ($line) => [
            'extra' => $line->extra,
            'quantity' => (int) $line->quantity,
            'total' => $line->total,
        ]);

        $categorySubtotal = bcmul((string) $booking->category_daily_price, (string) max(1, (int) $booking->rental_days), 2);

        $available = $vehicle
            ? $this->availability->isAvailable($vehicle, $booking->pickup_at, $booking->return_at)
            : $this->availability->categoryIsAvailable(
                $booking->category,
                $booking->pickup_at,
                $booking->return_at,
                (int) $booking->pickup_station_id
            );

        return view('public.booking.review', [
            'vehicle' => $vehicle,
            'booking' => $booking,
            'displayName' => $vehicle?->name() ?? $booking->category?->name ?? __('body.booking'),
            'dailyPrice' => $booking->category_daily_price,
            'pickupAt' => $booking->pickup_at,
            'returnAt' => $booking->return_at,
            'rentalDays' => max(1, (int) $booking->rental_days),
            'categorySubtotal' => $categorySubtotal,
            'extraLines' => $extraLines,
            'extrasTotal' => $booking->extras_total,
            'chauffeurFee' => $booking->chauffeur_fee ?? 0,
            'deliveryFee' => $booking->delivery_fee ?? 0,
            'collectionFee' => $booking->collection_fee ?? 0,
            'pickupMethod' => $booking->pickup_method,
            'returnMethod' => $booking->return_method,
            'totalAmount' => $booking->total_amount,
            'available' => $available,
            'stations' => $this->stations->activeOptions(),
            'paymentGateways' => SimulatedPaymentGateway::cases(),
            'formAction' => route('account.bookings.pay.confirm', $booking),
            'backHref' => route('account.bookings.show', $booking),
            'cancelHref' => route('account.bookings.show', $booking),
        ]);
    }

    public function confirmPay(ConfirmBookingPaymentRequest $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        if ($booking->status !== BookingStatus::Pending) {
            return redirect()
                ->route('account.bookings.show', $booking)
                ->with('error', __('body.booking_invalid_transition'));
        }

        $gateway = SimulatedPaymentGateway::from($request->validated('payment_gateway'));
        $notes = trim((string) ($booking->notes ?? ''));
        $simNote = '[Simulated payment via '.$gateway->label().']';
        $booking->notes = $notes === '' ? $simNote : $notes."\n".$simNote;
        $booking->save();

        $confirm = $this->bookings->confirm($booking);

        if (! $confirm['status']) {
            return redirect()
                ->route('account.bookings.pay', $booking)
                ->with('error', $confirm['message']);
        }

        return redirect()
            ->route('booking.confirmation', $booking)
            ->with('success', __('body.payment_simulated_success'))
            ->with('payment_gateway', $gateway->label());
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        $result = $this->bookings->cancel($booking, $request->validated('reason'));

        return redirect()
            ->route('account.bookings.show', $booking)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function extend(ExtendBookingRequest $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->customer_id === auth('customer')->id(), 404);

        $result = $this->bookings->extend($booking, $request->validated('new_return_at'));

        return redirect()
            ->route('account.bookings.show', $booking)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array<int, int>
     */
    protected function parseRequestedExtras(Request $request): array
    {
        $raw = $request->query('extras', []);

        if (! is_array($raw)) {
            $raw = filled($raw) ? [$raw] : [];
        }

        $quantities = [];
        $isList = array_is_list($raw);

        foreach ($raw as $key => $value) {
            if ($isList) {
                $id = (int) $value;

                if ($id > 0) {
                    $quantities[$id] = 1;
                }

                continue;
            }

            $id = (int) $key;
            $qty = (int) $value;

            if ($id > 0 && $qty > 0) {
                $quantities[$id] = min(20, $qty);
            }
        }

        return $quantities;
    }
}
