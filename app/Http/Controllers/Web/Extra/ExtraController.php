<?php

namespace App\Http\Controllers\Web\Extra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Extra\StoreExtraRequest;
use App\Http\Requests\Extra\UpdateExtraRequest;
use App\Models\Extra\Extra;
use App\Services\Extra\ExtraService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Extra\UpdateExtraStatusRequest;
use App\Enums\ExtraStatus;

class ExtraController extends Controller
{
    public function __construct(protected ExtraService $extras)
    {
    }

    public function index(): View
    {
        return view('app.extras.index', [
            'extras' => $this->extras->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->extras->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->extras->exportList());
    }

    public function create(): View
    {
        return view('app.extras.create');
    }

    public function store(StoreExtraRequest $request): RedirectResponse
    {
        $result = $this->extras->create($request->validated());

        return redirect()->route('extras.index')->with('success', $result['message']);
    }

    public function show(Extra $extra): View
    {
        return view('app.extras.show', [
            'extra' => $extra,
        ]);
    }

    public function edit(Extra $extra): View
    {
        return view('app.extras.edit', [
            'extra' => $extra,
        ]);
    }

    public function update(UpdateExtraRequest $request, Extra $extra): RedirectResponse
    {
        $result = $this->extras->update($extra, $request->validated());

        return redirect()->route('extras.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateExtraStatusRequest $request, Extra $extra): RedirectResponse
    {
        $status = ExtraStatus::from($request->validated('status'));
        $result = $this->extras->updateStatus($extra, $status);

        return redirect()
            ->route('extras.show', $extra)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Extra $extra): RedirectResponse
    {
        $result = $this->extras->delete($extra);

        return redirect()->route('extras.index')->with('success', $result['message']);
    }
}
