<?php

namespace App\Http\Controllers\Web\CorporateCustomer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorporateCustomer\StoreCorporateCustomerRequest;
use App\Http\Requests\CorporateCustomer\UpdateCorporateCustomerRequest;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Services\CorporateCustomer\CorporateCustomerService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\CorporateCustomer\UpdateCorporateCustomerStatusRequest;
use App\Enums\CorporateCustomerStatus;

class CorporateCustomerController extends Controller
{
    public function __construct(protected CorporateCustomerService $corporateCustomers)
    {
    }

    public function index(): View
    {
        return view('app.customers.corporate.index', [
            'corporateCustomers' => $this->corporateCustomers->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->corporateCustomers->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->corporateCustomers->exportList());
    }

    public function create(): View
    {
        return view('app.customers.corporate.create');
    }

    public function store(StoreCorporateCustomerRequest $request): RedirectResponse
    {
        $result = $this->corporateCustomers->create($request->validated());

        return redirect()->route('customers.corporate.index')->with('success', $result['message']);
    }

    public function show(CorporateCustomer $corporateCustomer): View
    {
        return view('app.customers.corporate.show', [
            'corporateCustomer' => $corporateCustomer->load('contacts'),
        ]);
    }

    public function edit(CorporateCustomer $corporateCustomer): View
    {
        return view('app.customers.corporate.edit', [
            'corporateCustomer' => $corporateCustomer->load('contacts'),
        ]);
    }

    public function update(UpdateCorporateCustomerRequest $request, CorporateCustomer $corporateCustomer): RedirectResponse
    {
        $result = $this->corporateCustomers->update($corporateCustomer, $request->validated());

        return redirect()->route('customers.corporate.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateCorporateCustomerStatusRequest $request, CorporateCustomer $corporateCustomer): RedirectResponse
    {
        $status = CorporateCustomerStatus::from($request->validated('status'));
        $result = $this->corporateCustomers->updateStatus($corporateCustomer, $status);

        return redirect()
            ->route('customers.corporate.show', $corporateCustomer)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(CorporateCustomer $corporateCustomer): RedirectResponse
    {
        $result = $this->corporateCustomers->delete($corporateCustomer);

        return redirect()->route('customers.corporate.index')->with('success', $result['message']);
    }
}
