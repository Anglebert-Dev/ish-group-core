<?php

namespace App\Http\Controllers\Web\FuelLog;

use App\Enums\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FuelLog\StoreFuelLogRequest;
use App\Http\Requests\FuelLog\UpdateFuelLogRequest;
use App\Libraries\ListExportLibrary;
use App\Models\Driver\Driver;
use App\Models\FuelLog\FuelLog;
use App\Models\Vehicle\Vehicle;
use App\Services\FuelLog\FuelLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\FuelLog\UpdateFuelLogStatusRequest;
use App\Enums\FuelLogStatus;

class FuelLogController extends Controller
{
    public function __construct(protected FuelLogService $fuelLogs)
    {
    }

    public function index(): View
    {
        return view('app.fleet.fuel_logs.index', [
            'fuelLogs' => $this->fuelLogs->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->fuelLogs->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->fuelLogs->exportList());
    }

    public function create(): View
    {
        return view('app.fleet.fuel_logs.create', $this->formData());
    }

    public function store(StoreFuelLogRequest $request): RedirectResponse
    {
        $result = $this->fuelLogs->create($request->validated());

        return redirect()->route('fleet.fuel_logs.index')->with('success', $result['message']);
    }

    public function show(FuelLog $fuelLog): View
    {
        return view('app.fleet.fuel_logs.show', [
            'fuelLog' => $fuelLog->load(['vehicle', 'driver']),
        ]);
    }

    public function edit(FuelLog $fuelLog): View
    {
        return view('app.fleet.fuel_logs.edit', [
            'fuelLog' => $fuelLog->load(['vehicle', 'driver']),
            ...$this->formData(),
        ]);
    }

    public function update(UpdateFuelLogRequest $request, FuelLog $fuelLog): RedirectResponse
    {
        $result = $this->fuelLogs->update($fuelLog, $request->validated());

        return redirect()->route('fleet.fuel_logs.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateFuelLogStatusRequest $request, FuelLog $fuelLog): RedirectResponse
    {
        $status = FuelLogStatus::from($request->validated('status'));
        $result = $this->fuelLogs->updateStatus($fuelLog, $status);

        return redirect()
            ->route('fleet.fuel_logs.show', $fuelLog)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(FuelLog $fuelLog): RedirectResponse
    {
        $result = $this->fuelLogs->delete($fuelLog);

        return redirect()->route('fleet.fuel_logs.index')->with('success', $result['message']);
    }

    /**
     * @return array{vehicles: \Illuminate\Database\Eloquent\Collection, drivers: \Illuminate\Database\Eloquent\Collection}
     */
    protected function formData(): array
    {
        return [
            'vehicles' => Vehicle::query()
                ->with('owner')
                ->latest('id')
                ->get(),
            'drivers' => Driver::query()
                ->where('status', DriverStatus::Active->value)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ];
    }
}
