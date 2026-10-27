<?php

namespace App\Http\Controllers\Web\VehicleCategory;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleCategory\StoreVehicleCategoryRequest;
use App\Http\Requests\VehicleCategory\UpdateVehicleCategoryRequest;
use App\Models\VehicleCategory\VehicleCategory;
use App\Services\VehicleCategory\VehicleCategoryService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\VehicleCategory\UpdateVehicleCategoryStatusRequest;
use App\Enums\VehicleCategoryStatus;

class VehicleCategoryController extends Controller
{
    public function __construct(protected VehicleCategoryService $categories)
    {
    }

    public function index(): View
    {
        return view('app.fleet.categories.index', [
            'categories' => $this->categories->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->categories->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->categories->exportList());
    }

    public function create(): View
    {
        return view('app.fleet.categories.create');
    }

    public function store(StoreVehicleCategoryRequest $request): RedirectResponse
    {
        $result = $this->categories->create($request->safe()->except('image'), $request->file('image'));

        return redirect()->route('fleet.categories.index')->with('success', $result['message']);
    }

    public function show(VehicleCategory $category): View
    {
        $category->load(['vehicles' => fn ($query) => $query->orderBy('make')->orderBy('model')]);

        return view('app.fleet.categories.show', [
            'category' => $category,
        ]);
    }

    public function edit(VehicleCategory $category): View
    {
        return view('app.fleet.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateVehicleCategoryRequest $request, VehicleCategory $category): RedirectResponse
    {
        $result = $this->categories->update($category, $request->safe()->except('image'), $request->file('image'));

        return redirect()->route('fleet.categories.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateVehicleCategoryStatusRequest $request, VehicleCategory $category): RedirectResponse
    {
        $status = VehicleCategoryStatus::from($request->validated('status'));
        $result = $this->categories->updateStatus($category, $status);

        return redirect()
            ->route('fleet.categories.show', $category)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(VehicleCategory $category): RedirectResponse
    {
        $result = $this->categories->delete($category);

        return redirect()->route('fleet.categories.index')->with('success', $result['message']);
    }
}
