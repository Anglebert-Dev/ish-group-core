<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreDriverDocumentRequest;
use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverLicenseRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Http\Requests\Driver\UpdateDriverStatusRequest;
use App\Libraries\ListExportLibrary;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverDocument;
use App\Services\Driver\DriverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverController extends Controller
{
    public function __construct(protected DriverService $drivers)
    {
    }

    public function index(): View
    {
        return view('app.drivers.index', [
            'drivers' => $this->drivers->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->drivers->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->drivers->exportList());
    }

    public function create(): View
    {
        return view('app.drivers.create');
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $result = $this->drivers->create(
            $request->safe()->except('documents'),
            $request->file('documents', [])
        );

        return redirect()->route('drivers.index')->with('success', $result['message']);
    }

    public function show(Driver $driver): View
    {
        return view('app.drivers.show', [
            'driver' => $driver->load('documents'),
        ]);
    }

    public function edit(Driver $driver): View
    {
        return view('app.drivers.edit', [
            'driver' => $driver->load('documents'),
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $result = $this->drivers->update(
            $driver,
            $request->safe()->except('documents'),
            $request->file('documents', [])
        );

        return redirect()->route('drivers.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateDriverStatusRequest $request, Driver $driver): RedirectResponse
    {
        $status = DriverStatus::from($request->validated('status'));
        $result = $this->drivers->updateStatus($driver, $status);

        return redirect()
            ->route('drivers.show', $driver)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function updateLicense(UpdateDriverLicenseRequest $request, Driver $driver): RedirectResponse
    {
        $result = $this->drivers->updateLicense(
            $driver,
            $request->safe()->except(['license_document']),
            $request->file('license_document')
        );

        return redirect()
            ->route('drivers.show', ['driver' => $driver, 'tab' => 'overview'])
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $result = $this->drivers->delete($driver);

        return redirect()->route('drivers.index')->with('success', $result['message']);
    }

    public function uploadDocument(StoreDriverDocumentRequest $request, Driver $driver): RedirectResponse
    {
        $result = $this->drivers->uploadDocument($driver, $request->file('file'), $request->validated()['type']);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroyDocument(Driver $driver, DriverDocument $document): RedirectResponse
    {
        abort_unless($document->driver_id === $driver->id, 404);

        $result = $this->drivers->deleteDocument($document);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Driver documents (license, national ID, etc.) are sensitive, so they're
     * stored on the private disk and only ever served through this
     * permission-gated route — never a public/symlinked path.
     */
    public function downloadDocument(Driver $driver, DriverDocument $document): StreamedResponse
    {
        abort_unless($document->driver_id === $driver->id, 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}
