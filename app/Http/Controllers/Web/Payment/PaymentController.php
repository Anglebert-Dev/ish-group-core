<?php

namespace App\Http\Controllers\Web\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Booking\Booking;
use App\Models\Expense\Expense;
use App\Models\Invoice\Invoice;
use App\Models\Payment\Payment;
use App\Models\Supplier\Supplier;
use App\Services\Payment\PaymentService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {
    }

    public function index(): View
    {
        return view('app.finance.payments.index', [
            'payments' => $this->payments->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->payments->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->payments->exportList());
    }

    public function create(): View
    {
        return view('app.finance.payments.create', [
            'bookings' => Booking::query()->latest()->limit(100)->get(),
            'invoices' => Invoice::query()->latest()->limit(100)->get(),
            'expenses' => Expense::query()->latest()->limit(100)->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $result = $this->payments->create($request->validated());

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.payments.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Payment $payment): View
    {
        return view('app.finance.payments.show', [
            'payment' => $payment->load(['booking', 'invoice', 'expense', 'supplier']),
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $result = $this->payments->delete($payment);

        return redirect()->route('finance.payments.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
