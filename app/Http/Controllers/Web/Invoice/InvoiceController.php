<?php

namespace App\Http\Controllers\Web\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Models\Booking\Booking;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoices)
    {
    }

    public function index(): View
    {
        return view('app.finance.invoices.index', [
            'invoices' => $this->invoices->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->invoices->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->invoices->exportList());
    }

    public function create(): View
    {
        return view('app.finance.invoices.create', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'corporates' => CorporateCustomer::query()->orderBy('company_name')->get(),
            'bookings' => Booking::query()->latest()->limit(100)->get(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $result = $this->invoices->create($request->validated());

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.invoices.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Invoice $invoice): View
    {
        return view('app.finance.invoices.show', [
            'invoice' => $invoice->load(['lines', 'customer', 'corporateCustomer', 'booking']),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        return view('app.finance.invoices.edit', [
            'invoice' => $invoice->load('lines'),
            'customers' => Customer::query()->orderBy('name')->get(),
            'corporates' => CorporateCustomer::query()->orderBy('company_name')->get(),
            'bookings' => Booking::query()->latest()->limit(100)->get(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->update($invoice, $request->validated());

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.invoices.show', $invoice)->with('success', $result['message']);
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->issue($invoice);

        return redirect()->route('finance.invoices.show', $invoice)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->cancel($invoice);

        return redirect()->route('finance.invoices.show', $invoice)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->delete($invoice);

        return redirect()->route('finance.invoices.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
