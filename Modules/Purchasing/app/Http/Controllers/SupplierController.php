<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseReceipt;
use Modules\Purchasing\Models\Supplier;
use Modules\Purchasing\Models\SupplierPayment;
use Modules\Purchasing\Services\PurchasingService;

class SupplierController extends Controller
{
    public function __construct(protected PurchasingService $purchasing)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('purchasing::suppliers.index', [
            'suppliers' => Supplier::query()
                ->when($q, fn ($query) => $query->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($q).'%'])->orWhere('phone', 'like', "%{$q}%")))
                ->orderByDesc('is_active')->orderBy('name')->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('purchasing::suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = new Supplier($this->validated($request));
        $supplier->is_active = true;
        $supplier->save();

        return redirect()->route('purchasing.suppliers.show', $supplier)->with('success', __('purchasing::purchasing.supplier_saved'));
    }

    public function show(Supplier $supplier): View
    {
        return view('purchasing::suppliers.show', [
            'supplier' => $supplier,
            'balance' => $this->purchasing->balance($supplier),
            'orders' => PurchaseOrder::query()->where('supplier_id', $supplier->id)->latest('id')->limit(15)->get(),
            'receipts' => PurchaseReceipt::query()->where('supplier_id', $supplier->id)->latest('id')->limit(15)->get(),
            'payments' => SupplierPayment::query()->where('supplier_id', $supplier->id)->latest('paid_on')->latest('id')->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('purchasing::suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->fill($this->validated($request) + ['is_active' => $request->boolean('is_active')])->save();

        return redirect()->route('purchasing.suppliers.show', $supplier)->with('success', __('purchasing::purchasing.supplier_saved'));
    }

    public function pay(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(['cash', 'mobile_money', 'bank', 'cheque', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $result = $this->purchasing->pay($supplier, $data);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function reversePayment(Request $request, Supplier $supplier, SupplierPayment $payment): RedirectResponse
    {
        abort_unless($payment->supplier_id === $supplier->id, 404);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->purchasing->reversePayment($payment, $request->input('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
