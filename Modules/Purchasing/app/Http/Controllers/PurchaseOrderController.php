<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StoreService;
use Modules\Inventory\Support\DocumentLines;
use Modules\Purchasing\Enums\PurchaseStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\Supplier;
use Modules\Purchasing\Services\PurchasingService;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchasingService $purchasing,
        protected ItemService $items,
        protected StoreService $stores,
    ) {
    }

    public function index(Request $request): View
    {
        $status = PurchaseStatus::tryFrom((string) $request->query('status'));

        return view('purchasing::orders.index', [
            'orders' => PurchaseOrder::query()->with('supplier')
                ->when($status, fn ($q) => $q->where('status', $status->value))
                ->latest('order_date')->latest('id')->paginate(20)->withQueryString(),
            'status' => $status?->value,
        ]);
    }

    public function reorder(): View
    {
        return view('purchasing::orders.reorder', ['rows' => $this->purchasing->reorderList()]);
    }

    public function create(Request $request): View
    {
        $order = new PurchaseOrder(['order_date' => now()]);
        $order->setRelation('lines', collect());
        $rows = [];

        // From the reorder list: ?variants[uuid]=quantity
        foreach ((array) $request->query('variants', []) as $uuid => $quantity) {
            $variant = Variant::query()->where('uuid', $uuid)->first();
            if ($variant) {
                $rows[] = ['variant' => $uuid, 'quantity' => (float) $quantity, 'unit_price' => (float) $variant->cost_price];
            }
        }

        return $this->form($order, $rows);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);
        $result = $this->purchasing->createOrder($data, $lines);

        return redirect()->route('purchasing.orders.show', $result['data'])->with('success', $result['message']);
    }

    public function show(PurchaseOrder $order): View
    {
        return view('purchasing::orders.show', ['order' => $order->load(['supplier', 'store', 'lines.variant.item', 'receipts'])]);
    }

    public function edit(PurchaseOrder $order): View|RedirectResponse
    {
        if ($order->status !== PurchaseStatus::Draft) {
            return redirect()->route('purchasing.orders.show', $order)->with('error', __('purchasing::purchasing.order_locked'));
        }

        $order->load('lines.variant');

        return $this->form($order, $order->lines->map(fn ($l) => [
            'variant' => $l->variant?->uuid, 'description' => $l->description, 'quantity' => (float) $l->quantity, 'unit_price' => (float) $l->unit_cost,
        ])->all());
    }

    public function update(Request $request, PurchaseOrder $order): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);
        $result = $this->purchasing->updateOrder($order, $data, $lines);

        return redirect()->route('purchasing.orders.show', $order)->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function send(PurchaseOrder $order): RedirectResponse
    {
        $result = $this->purchasing->markOrdered($order);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->purchasing->cancelOrder($order, $request->input('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->purchasing->receive($order, $data['quantities'], collect($data)->except('quantities')->all());

        return back()->withInput($result['status'] ? [] : $request->input())->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier' => ['required', 'string'],
            'store' => ['required', 'string'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + DocumentLines::rules());

        $supplier = Supplier::query()->where('uuid', $data['supplier'])->firstOrFail();
        $store = Store::query()->where('uuid', $data['store'])->where('is_active', true)->firstOrFail();

        return [[
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], DocumentLines::resolve((array) $request->input('lines', []))];
    }

    private function form(PurchaseOrder $order, array $rows): View
    {
        return view('purchasing::orders.form', [
            'order' => $order,
            'rows' => $rows,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
            'defaultStore' => $this->stores->default(auth('staff')->user()->business)->uuid,
            'lineOptions' => $this->items->lineOptions(null, true),
        ]);
    }
}
