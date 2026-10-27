<?php

namespace App\Http\Controllers\Web;

use App\Enums\StationStatus;
use App\Enums\VehicleCategoryStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function index(): View
    {
        $vehicles = Vehicle::query()
            ->with(['category', 'images', 'station'])
            ->where('status', VehicleStatus::Available->value)
            ->latest()
            ->take(12)
            ->get();

        $totalCount = Vehicle::query()
            ->where('status', VehicleStatus::Available->value)
            ->count();

        $types = $vehicles
            ->pluck('vehicle_type')
            ->filter()
            ->unique(fn (VehicleType $type) => $type->value)
            ->values();

        $categories = VehicleCategory::query()
            ->where('status', VehicleCategoryStatus::Active->value)
            ->whereHas('vehicles', fn ($q) => $q->where('status', VehicleStatus::Available->value))
            ->orderBy('name')
            ->get(['id', 'name']);

        $makes = Vehicle::query()
            ->where('status', VehicleStatus::Available->value)
            ->whereNotNull('make')
            ->distinct()
            ->orderBy('make')
            ->pluck('make');

        $stations = Station::where('status', StationStatus::Active->value)->orderBy('name')->get();

        return view('public.home', [
            'vehicles' => $vehicles,
            'stations' => $stations,
            'makes' => $makes,
            'categories' => $categories,
            'types' => $types,
            'totalCount' => $totalCount,
        ]);
    }
}
