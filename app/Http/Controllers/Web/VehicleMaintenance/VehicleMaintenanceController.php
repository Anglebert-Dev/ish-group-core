<?php

namespace App\Http\Controllers\Web\VehicleMaintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleMaintenance\StoreVehicleMaintenanceRequest;
use App\Http\Requests\VehicleMaintenance\UpdateVehicleMaintenanceRequest;
use App\Http\Requests\VehicleMaintenance\UpdateVehicleMaintenanceStatusRequest;
use App\Enums\MaintenanceStatus;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Services\VehicleMaintenance\VehicleMaintenanceService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleMaintenanceController extends Controller
{
    public function __construct(protected VehicleMaintenanceService $maintenances)
    {
    }

    public function index(): View
    {
        return view('app.fleet.maintenance.index', [
            'maintenances' => $this->maintenances->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->maintenances->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->maintenances->exportList());
    }

    public function create(): View
    {
        return view('app.fleet.maintenance.create', [
            'vehicles' => Vehicle::query()->with('owner')->latest('id')->get(),
        ]);
    }

    public function store(StoreVehicleMaintenanceRequest $request): RedirectResponse
    {
        $result = $this->maintenances->create($request->validated());

        return redirect()
            ->route('fleet.maintenance.show', $result['data'])
            ->with('success', $result['message']);
    }

    public function show(VehicleMaintenance $maintenance): View
    {
        return view('app.fleet.maintenance.show', [
            'maintenance' => $maintenance->load('vehicle'),
        ]);
    }

    public function edit(VehicleMaintenance $maintenance): View
    {
        return view('app.fleet.maintenance.edit', [
            'maintenance' => $maintenance->load('vehicle'),
            'vehicles' => Vehicle::query()->with('owner')->latest('id')->get(),
        ]);
    }

    public function update(UpdateVehicleMaintenanceRequest $request, VehicleMaintenance $maintenance): RedirectResponse
    {
        $result = $this->maintenances->update($maintenance, $request->validated());

        return redirect()->route('fleet.maintenance.show', $maintenance)->with('success', $result['message']);
    }

    public function updateStatus(UpdateVehicleMaintenanceStatusRequest $request, VehicleMaintenance $maintenance): RedirectResponse
    {
        $status = MaintenanceStatus::from($request->validated('status'));
        $result = $this->maintenances->updateStatus($maintenance, $status);

        return redirect()
            ->route('fleet.maintenance.show', $maintenance)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(VehicleMaintenance $maintenance): RedirectResponse
    {
        $result = $this->maintenances->delete($maintenance);

        return redirect()->route('fleet.maintenance.index')->with('success', $result['message']);
    }
}
