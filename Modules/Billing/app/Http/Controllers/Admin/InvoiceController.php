<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Billing\Http\Requests\SubmitPaymentRequest;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceService;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Support\BillingExports;
use Modules\Billing\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
        protected PaymentService $payments,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status']);

        return view('billing::admin.invoices.index', [
            'invoices' => $this->filtered($filters)->with('business')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $export = BillingExports::invoices($this->filtered($request->only(['q', 'status'])));

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    private function filtered(array $filters): Builder
    {
        return Invoice::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'overdue'
                ? $q->where('status', 'open')->where('due_at', '<', now())
                : $q->where('status', $status))
            ->when(trim((string) ($filters['q'] ?? '')) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', '%'.trim($filters['q']).'%')
                ->orWhereHas('business', fn ($b) => $b->where('name', 'like', '%'.trim($filters['q']).'%'))))
            ->latest('id');
    }

    public function show(Invoice $invoice): View
    {
        return view('billing::admin.invoices.show', [
            'invoice' => $invoice->load(['business', 'lines', 'payments.submitter', 'payments.reviewer', 'subscription']),
        ]);
    }

    public function pdf(Invoice $invoice): Response
    {
        return InvoicePdf::download($invoice);
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->void($invoice, (string) $request->input('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Superadmin records money received offline: approved immediately.
     */
    public function recordPayment(SubmitPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $result = $this->payments->submit(
            $invoice,
            Auth::guard('staff')->user(),
            $request->safe()->except('proof'),
            $request->file('proof'),
            approveNow: true,
        );

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
