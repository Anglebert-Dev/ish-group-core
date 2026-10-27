<?php

namespace App\Http\Controllers\Web\Vehicle;

use App\Enums\FuelType;
use App\Enums\StationStatus;
use App\Enums\TransmissionType;
use App\Enums\VehicleCategoryStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Libraries\GeneralLibrary;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use App\Services\Availability\VehicleAvailabilityService;
use App\Services\Extra\ExtraService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The public /vehicles listing + detail pages — only ever shows vehicles
 * with status = Available, since these are what a customer could actually book.
 */
class PublicVehicleController extends Controller
{
    public function __construct(
        protected VehicleAvailabilityService $availability,
        protected ExtraService $extras,
    ) {
    }

    public function index(Request $request): View
    {
        return view('public.vehicles.index', $this->filteredResults($request));
    }

    public function search(Request $request): View
    {
        return view('public.booking.search', $this->filteredResults($request));
    }

    public function show(Vehicle $vehicle): View
    {
        abort_unless($vehicle->status === VehicleStatus::Available, 404);

        $vehicle->load(['category', 'station', 'images']);

        $pickupRaw = (string) request()->query('pickup_at', '');
        $returnRaw = (string) request()->query('return_at', '');
        $availableForDates = true;

        if ($pickupRaw !== '' && $returnRaw !== '') {
            try {
                $pickupAt = GeneralLibrary::parseAppDateTime($pickupRaw);
                $returnAt = GeneralLibrary::parseAppDateTime($returnRaw);
                if ($pickupAt->lt($returnAt)) {
                    $availableForDates = $this->availability->isAvailable($vehicle, $pickupAt, $returnAt);
                }
            } catch (\Throwable) {
                $availableForDates = true;
            }
        }

        return view('public.vehicles.show', [
            'vehicle' => $vehicle,
            'optionalExtras' => $this->extras->optionalCatalog(),
            'chauffeurDailyFee' => $this->extras->chauffeurDailyFee(),
            'blockedRanges' => $this->availability->blockingRanges($vehicle),
            'availableForDates' => $availableForDates,
        ]);
    }

    protected function filteredResults(Request $request): array
    {
        $query = Vehicle::query()->with(['category', 'images', 'station']);

        $dateRange = $this->parseDateRange($request);

        if ($dateRange) {
            $this->availability->applyAvailability($query, $dateRange[0], $dateRange[1]);
        } else {
            $query->where('vehicles.status', VehicleStatus::Available->value);
        }

        if ($search = trim((string) $request->string('search'))) {
            $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($tokens as $token) {
                $like = '%'.addcslashes($token, '%_\\').'%';
                $query->where(function ($q) use ($like, $token) {
                    $compact = preg_replace('/\s+/', '', $token) ?: $token;
                    $compactLike = '%'.addcslashes($compact, '%_\\').'%';

                    $q->where('vehicles.make', 'like', $like)
                        ->orWhere('vehicles.model', 'like', $like)
                        ->orWhere('vehicles.plate_number', 'like', $like)
                        ->orWhereRaw("REPLACE(vehicles.plate_number, ' ', '') like ?", [$compactLike])
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like));
                });
            }
        }

        if ($categoryUuid = trim((string) $request->string('category'))) {
            $categoryId = VehicleCategory::query()->where('uuid', $categoryUuid)->value('id');
            if ($categoryId) {
                $query->where('vehicles.vehicle_category_id', $categoryId);
            }
        }

        if ($stationUuid = trim((string) $request->string('station'))) {
            $stationId = Station::query()->where('uuid', $stationUuid)->value('id');
            if ($stationId) {
                $query->where('vehicles.station_id', $stationId);
            }
        }

        $transmission = (string) $request->string('transmission');

        if ($transmission !== '' && TransmissionType::tryFrom($transmission)) {
            $query->where('vehicles.transmission', $transmission);
        }

        $fuelType = (string) $request->string('fuel_type');

        if ($fuelType !== '' && FuelType::tryFrom($fuelType)) {
            $query->where('vehicles.fuel_type', $fuelType);
        }

        if (is_numeric($request->input('min_price'))) {
            $query->where('vehicles.daily_price', '>=', (float) $request->input('min_price'));
        }

        if (is_numeric($request->input('max_price'))) {
            $query->where('vehicles.daily_price', '<=', (float) $request->input('max_price'));
        }

        $sort = (string) $request->string('sort');

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $query->orderBy('vehicles.daily_price', $sort === 'price_asc' ? 'asc' : 'desc');
        } else {
            $query->latest('vehicles.created_at');
        }

        return [
            'vehicles' => $query->paginate(9)->withQueryString(),
            'categories' => VehicleCategory::where('status', VehicleCategoryStatus::Active)->orderBy('name')->get(),
            'stations' => Station::where('status', StationStatus::Active)->orderBy('name')->get(),
            'filters' => $request->only(['search', 'category', 'station', 'transmission', 'fuel_type', 'min_price', 'max_price', 'sort', 'pickup_date', 'return_date', 'rental_type', 'return_station', 'round_trip']),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function parseDateRange(Request $request): ?array
    {
        if (! $request->filled('pickup_date') || ! $request->filled('return_date')) {
            return null;
        }

        try {
            $pickupRaw = (string) $request->string('pickup_date');
            $returnRaw = (string) $request->string('return_date');
            $pickupAt = GeneralLibrary::parseAppDateTime($pickupRaw);
            $returnAt = GeneralLibrary::parseAppDateTime($returnRaw);

            if (! str_contains($pickupRaw, 'T') && strlen($pickupRaw) <= 10) {
                $pickupAt = $pickupAt->startOfDay();
            }

            if (! str_contains($returnRaw, 'T') && strlen($returnRaw) <= 10) {
                $returnAt = $returnAt->endOfDay();
            }
        } catch (\Throwable) {
            return null;
        }

        return $pickupAt->lte($returnAt) ? [$pickupAt, $returnAt] : null;
    }
}
