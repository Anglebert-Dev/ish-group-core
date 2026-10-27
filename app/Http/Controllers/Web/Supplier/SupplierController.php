<?php

namespace App\Http\Controllers\Web\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier\Supplier;
use App\Services\Supplier\SupplierService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Supplier\UpdateSupplierStatusRequest;
use App\Enums\SupplierStatus;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $suppliers)
    {
    }

    public function index(): View
    {
        return view('app.finance.suppliers.index', [
            'suppliers' => $this->suppliers->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->suppliers->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->suppliers->exportList());
    }

    public function create(): View
    {
        return view('app.finance.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $result = $this->suppliers->create($request->validated());

        return redirect()->route('finance.suppliers.index')->with('success', $result['message']);
    }

    public function show(Supplier $supplier): View
    {
        return view('app.finance.suppliers.show', [
            'supplier' => $supplier,
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('app.finance.suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $result = $this->suppliers->update($supplier, $request->validated());

        return redirect()->route('finance.suppliers.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateSupplierStatusRequest $request, Supplier $supplier): RedirectResponse
    {
        $status = SupplierStatus::from($request->validated('status'));
        $result = $this->suppliers->updateStatus($supplier, $status);

        return redirect()
            ->route('finance.suppliers.show', $supplier)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $result = $this->suppliers->delete($supplier);

        return redirect()->route('finance.suppliers.index')->with('success', $result['message']);
    }
}
