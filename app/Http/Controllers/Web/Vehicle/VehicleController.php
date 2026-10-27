<?php

namespace App\Http\Controllers\Web\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\StoreVehicleImagesRequest;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleComplianceRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleStatusRequest;
use App\Enums\VehicleStatus;
use App\Libraries\ListExportLibrary;
use App\Libraries\VehicleCatalogLibrary;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleImage;
use App\Models\VehicleCategory\VehicleCategory;
use App\Services\Vehicle\VehicleService;
use App\Services\VehicleOwner\VehicleOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    public function __construct(
        protected VehicleService $vehicles,
        protected VehicleOwnerService $owners,
    ) {
    }

    public function index(): View
    {
        return view('app.fleet.vehicles.index', [
            'vehicles' => $this->vehicles->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->vehicles->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->vehicles->exportList());
    }

    public function create(): View
    {
        return view('app.fleet.vehicles.create', $this->formOptions());
    }

    public function catalogModels(Request $request): JsonResponse
    {
        $make = trim((string) $request->query('make', ''));
        $models = VehicleCatalogLibrary::modelsFor($make);

        return response()->json([
            'make' => $make,
            'models' => $models,
            'model_categories' => VehicleCatalogLibrary::rentalCategoriesFor($make, $models),
            'model_specs' => VehicleCatalogLibrary::specsFor($make, $models),
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $result = $this->vehicles->create(
            $request->safe()->except(['images', 'ownership_type', 'insurance_document', 'inspection_document', 'carte_jaune_document']),
            $request->file('images', []),
            [
                'insurance_document' => $request->file('insurance_document'),
                'inspection_document' => $request->file('inspection_document'),
                'carte_jaune_document' => $request->file('carte_jaune_document'),
            ]
        );

        return redirect()->route('fleet.vehicles.index')->with('success', $result['message']);
    }

    public function show(Vehicle $vehicle): View
    {
        return view('app.fleet.vehicles.show', [
            'vehicle' => $vehicle->load(['category', 'station', 'owner', 'images']),
        ]);
    }

    public function uploadImages(StoreVehicleImagesRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $result = $this->vehicles->uploadImages($vehicle, $request->file('images'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroyImage(Vehicle $vehicle, VehicleImage $image): RedirectResponse
    {
        abort_unless($image->vehicle_id === $vehicle->id, 404);

        $result = $this->vehicles->deleteImage($image);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function edit(Vehicle $vehicle): View
    {
        $options = $this->formOptions();

        if ($vehicle->vehicle_owner_id && ! $options['owners']->contains('id', $vehicle->vehicle_owner_id)) {
            $currentOwner = $vehicle->owner()->first();
            if ($currentOwner) {
                $options['owners'] = $options['owners']->prepend($currentOwner)->unique('id')->values();
            }
        }

        return view('app.fleet.vehicles.edit', array_merge(
            ['vehicle' => $vehicle->load('images')],
            $options
        ));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $result = $this->vehicles->update(
            $vehicle,
            $request->safe()->except(['images', 'ownership_type', 'insurance_document', 'inspection_document', 'carte_jaune_document']),
            $request->file('images', []),
            [
                'insurance_document' => $request->file('insurance_document'),
                'inspection_document' => $request->file('inspection_document'),
                'carte_jaune_document' => $request->file('carte_jaune_document'),
            ]
        );

        return redirect()->route('fleet.vehicles.show', $vehicle)->with('success', $result['message']);
    }

    public function updateStatus(UpdateVehicleStatusRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $status = VehicleStatus::from($request->validated('status'));
        $result = $this->vehicles->updateStatus($vehicle, $status);

        return redirect()
            ->route('fleet.vehicles.show', $vehicle)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function updateCompliance(UpdateVehicleComplianceRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $result = $this->vehicles->updateCompliance(
            $vehicle,
            $request->safe()->except(['insurance_document', 'inspection_document', 'carte_jaune_document']),
            [
                'insurance_document' => $request->file('insurance_document'),
                'inspection_document' => $request->file('inspection_document'),
                'carte_jaune_document' => $request->file('carte_jaune_document'),
            ]
        );

        return redirect()
            ->route('fleet.vehicles.show', ['vehicle' => $vehicle, 'tab' => 'compliance'])
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $result = $this->vehicles->delete($vehicle);

        return redirect()->route('fleet.vehicles.index')->with('success', $result['message']);
    }

    protected function formOptions(): array
    {
        return [
            'categories' => VehicleCategory::orderBy('name')->get(),
            'stations' => Station::orderBy('name')->get(),
            'owners' => $this->owners->activeCatalog(),
        ];
    }
}
