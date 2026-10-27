<?php

namespace App\Http\Controllers\Web\VehicleOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleOwner\StoreVehicleOwnerRequest;
use App\Http\Requests\VehicleOwner\UpdateVehicleOwnerRequest;
use App\Http\Requests\VehicleOwner\UpdateVehicleOwnerStatusRequest;
use App\Enums\VehicleOwnerStatus;
use App\Models\VehicleOwner\VehicleOwner;
use App\Services\VehicleOwner\VehicleOwnerService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleOwnerController extends Controller
{
    public function __construct(protected VehicleOwnerService $owners)
    {
    }

    public function index(): View
    {
        return view('app.fleet.owners.index', [
            'owners' => $this->owners->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->owners->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->owners->exportList());
    }

    public function create(): View
    {
        return view('app.fleet.owners.create');
    }

    public function store(StoreVehicleOwnerRequest $request): RedirectResponse
    {
        $result = $this->owners->create($request->validated());

        return redirect()->route('fleet.owners.index')->with('success', $result['message']);
    }

    public function show(VehicleOwner $owner): View
    {
        return view('app.fleet.owners.show', [
            'owner' => $owner->load('vehicles.category'),
        ]);
    }

    public function edit(VehicleOwner $owner): View
    {
        return view('app.fleet.owners.edit', [
            'owner' => $owner,
        ]);
    }

    public function update(UpdateVehicleOwnerRequest $request, VehicleOwner $owner): RedirectResponse
    {
        $result = $this->owners->update($owner, $request->validated());

        return redirect()->route('fleet.owners.show', $owner)->with('success', $result['message']);
    }

    public function updateStatus(UpdateVehicleOwnerStatusRequest $request, VehicleOwner $owner): RedirectResponse
    {
        $status = VehicleOwnerStatus::from($request->validated('status'));
        $result = $this->owners->updateStatus($owner, $status);

        return redirect()
            ->route('fleet.owners.show', $owner)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(VehicleOwner $owner): RedirectResponse
    {
        $result = $this->owners->delete($owner);

        return redirect()->route('fleet.owners.index')->with('success', $result['message']);
    }
}
