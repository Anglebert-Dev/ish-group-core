<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StoreService;
use Modules\Inventory\Support\DocumentLines;
use Modules\Invoicing\Enums\PaymentMethod;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Invoicing\Models\SalesPayment;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Invoicing\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
        protected ItemService $items,
        protected StoreService $stores,
        protected ModuleManager $modules,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(InvoiceService::FILTERS);

        return view('invoicing::invoices.index', [
            'invoices' => $this->invoices->list($filters),
            'filters' => $filters,
            'owed' => (float) $this->invoices->filtered(['status' => 'open'])->selectRaw('COALESCE(SUM(total - paid), 0) as owed')->value('owed'),
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->invoices->exportList($request->only(InvoiceService::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->invoices->exportList($request->only(InvoiceService::FILTERS)));
    }

    public function create(Request $request): View
    {
        $invoice = new SalesInvoice(['invoice_date' => now(), 'due_date' => now()->addDays((int) config('invoicing.due_days', 0))]);
        $rows = [];
        $salesOrder = null;

        if ($request->filled('sales_order') && $this->salesOrdersActive()) {
            $salesOrder = \Modules\SalesOrders\Models\SalesOrder::query()->where('uuid', $request->query('sales_order'))->with('lines.variant')->first();
            if ($salesOrder) {
                $invoice->fill(['customer_id' => $salesOrder->customer_id, 'store_id' => $salesOrder->store_id, 'sales_order_id' => $salesOrder->id, 'discount' => $salesOrder->discount]);
                $rows = $this->invoices->linesFromOrder($salesOrder);
            }
        } elseif ($request->filled('customer')) {
            $invoice->customer_id = Customer::query()->where('uuid', $request->query('customer'))->value('id');
        }

        return view('invoicing::invoices.form', [
            'invoice' => $invoice,
            'rows' => $rows,
            'salesOrder' => $salesOrder,
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get()
                ->mapWithKeys(fn (Customer $c) => [$c->uuid => $c->displayName().($c->phone ? ' · '.$c->phone : '')])->all(),
            'selectedCustomer' => $invoice->customer_id ? Customer::query()->whereKey($invoice->customer_id)->value('uuid') : null,
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
            'selectedStore' => $invoice->store_id ? Store::query()->whereKey($invoice->store_id)->value('uuid') : $this->stores->default(auth('staff')->user()->business)->uuid,
            'lineOptions' => $this->items->lineOptions(),
            'taxRate' => $this->invoices->taxRate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer' => ['required', 'string'],
            'store' => ['required', 'string'],
            'sales_order' => ['nullable', 'string'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + DocumentLines::rules());

        $customer = Customer::query()->where('uuid', $data['customer'])->firstOrFail();
        $store = Store::query()->where('uuid', $data['store'])->where('is_active', true)->firstOrFail();
        $salesOrderId = filled($data['sales_order'] ?? null) && $this->salesOrdersActive()
            ? \Modules\SalesOrders\Models\SalesOrder::query()->where('uuid', $data['sales_order'])->value('id')
            : null;

        $result = $this->invoices->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'sales_order_id' => $salesOrderId,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'discount' => (float) ($data['discount'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ], DocumentLines::resolve((array) $request->input('lines', [])));

        return $result['status']
            ? redirect()->route('invoicing.invoices.show', $result['data'])->with('success', $result['message'])
            : back()->withInput()->with('error', $result['message']);
    }

    public function show(SalesInvoice $invoice): View
    {
        return view('invoicing::invoices.show', [
            'invoice' => $invoice->load(['customer', 'store', 'lines.variant.item', 'payments']),
            'salesOrder' => $invoice->sales_order_id && $this->salesOrdersActive()
                ? \Modules\SalesOrders\Models\SalesOrder::query()->find($invoice->sales_order_id)
                : null,
            'methods' => collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all(),
        ]);
    }

    public function pdf(SalesInvoice $invoice): Response
    {
        return InvoicePdf::download($invoice);
    }

    public function pay(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->invoices->addPayment($invoice, $data);

        return back()->withInput($result['status'] ? [] : $request->input())->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function reversePayment(Request $request, SalesInvoice $invoice, SalesPayment $payment): RedirectResponse
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->invoices->reversePayment($payment, $request->input('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->invoices->cancel($invoice, $request->input('reason'), $request->boolean('return_stock'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function settings(BusinessSettings $settings): View
    {
        return view('invoicing::settings', [
            'values' => [
                'tax_rate' => $settings->get('invoicing.tax_rate', 0),
                'tax_id' => $settings->get('invoicing.tax_id'),
                'address' => $settings->get('invoicing.address'),
                'phone' => $settings->get('invoicing.phone'),
                'footer' => $settings->get('invoicing.footer'),
            ],
        ]);
    }

    public function saveSettings(Request $request, BusinessSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'footer' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data as $key => $value) {
            $settings->set('invoicing.'.$key, $key === 'tax_rate' ? (float) $value : ($value ?: null));
        }

        return back()->with('success', __('invoicing::invoicing.settings_saved'));
    }

    private function salesOrdersActive(): bool
    {
        return $this->modules->isActive('SalesOrders') && (bool) auth('staff')->user()?->business?->canUseModule('SalesOrders');
    }
}
