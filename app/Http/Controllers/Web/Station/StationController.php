<?php

namespace App\Http\Controllers\Web\Station;

use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StoreStationRequest;
use App\Http\Requests\Station\UpdateStationRequest;
use App\Models\Station\Station;
use App\Services\Station\StationService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Station\UpdateStationStatusRequest;
use App\Enums\StationStatus;

class StationController extends Controller
{
    public function __construct(protected StationService $stations)
    {
    }

    public function index(): View
    {
        return view('app.stations.index', [
            'stations' => $this->stations->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->stations->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->stations->exportList());
    }

    public function create(): View
    {
        return view('app.stations.create');
    }

    public function store(StoreStationRequest $request): RedirectResponse
    {
        $result = $this->stations->create($request->validated());

        return redirect()->route('stations.index')->with('success', $result['message']);
    }

    public function show(Station $station): View
    {
        return view('app.stations.show', [
            'station' => $station,
        ]);
    }

    public function edit(Station $station): View
    {
        return view('app.stations.edit', [
            'station' => $station,
        ]);
    }

    public function update(UpdateStationRequest $request, Station $station): RedirectResponse
    {
        $result = $this->stations->update($station, $request->validated());

        return redirect()->route('stations.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateStationStatusRequest $request, Station $station): RedirectResponse
    {
        $status = StationStatus::from($request->validated('status'));
        $result = $this->stations->updateStatus($station, $status);

        return redirect()
            ->route('stations.show', $station)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Station $station): RedirectResponse
    {
        $result = $this->stations->delete($station);

        return redirect()->route('stations.index')->with('success', $result['message']);
    }
}
