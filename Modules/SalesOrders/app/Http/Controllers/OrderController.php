<?php

namespace Modules\SalesOrders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use App\Models\Customer\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Services\StoreService;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Http\Requests\SaveOrderRequest;
use Modules\SalesOrders\Models\SalesOrder;
use Modules\SalesOrders\Services\OrderService;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected ItemService $items,
        protected StoreService $stores,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(OrderService::FILTERS);

        return view('salesorders::orders.index', [
            'orders' => $this->orders->list($filters),
            'filters' => $filters,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->orders->exportList($request->only(OrderService::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->orders->exportList($request->only(OrderService::FILTERS)));
    }

    public function create(Request $request): View
    {
        $order = new SalesOrder(['order_date' => now()]);
        $order->setRelation('lines', collect());

        if ($request->filled('customer')) {
            $order->customer_id = Customer::query()->where('uuid', $request->query('customer'))->value('id');
        }

        return $this->form($order);
    }

    public function store(SaveOrderRequest $request): RedirectResponse
    {
        $result = $this->orders->create($request->orderData(), $request->lines());

        return redirect()->route('salesorders.orders.show', $result['data'])->with('success', $result['message']);
    }

    public function show(SalesOrder $order, StockService $stock): View
    {
        $order->load(['customer', 'store', 'lines.variant.item']);
        $variantIds = $order->lines->pluck('variant_id')->all();
        $available = [];

        foreach ($order->lines as $line) {
            $available[$line->variant_id] ??= $line->variant ? $stock->onHand($line->variant, $order->store) : 0;
        }

        return view('salesorders::orders.show', [
            'order' => $order,
            'available' => $available,
            'movements' => Movement::query()->where('reference_type', $order->getMorphClass())->where('reference_id', $order->id)->count(),
            'panels' => app(\App\Services\Module\ModuleManager::class)->hook('sales_order_panel', $order),
        ]);
    }

    public function edit(SalesOrder $order): View|RedirectResponse
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return redirect()->route('salesorders.orders.show', $order)->with('error', __('salesorders::orders.locked'));
        }

        return $this->form($order->load('lines.variant'));
    }

    public function update(SaveOrderRequest $request, SalesOrder $order): RedirectResponse
    {
        $result = $this->orders->update($order, $request->orderData(), $request->lines());

        return $result['status']
            ? redirect()->route('salesorders.orders.show', $order)->with('success', $result['message'])
            : back()->withInput()->with('error', $result['message']);
    }

    public function confirm(SalesOrder $order): RedirectResponse
    {
        return $this->respond($this->orders->confirm($order));
    }

    public function progress(Request $request, SalesOrder $order): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(array_map(fn ($s) => $s->value, OrderStatus::progress()))]]);

        return $this->respond($this->orders->progress($order, OrderStatus::from($request->input('status'))));
    }

    public function deliver(SalesOrder $order): RedirectResponse
    {
        return $this->respond($this->orders->deliver($order));
    }

    public function cancel(Request $request, SalesOrder $order): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->respond($this->orders->cancel($order, $request->input('reason')));
    }

    private function respond(array $result): RedirectResponse
    {
        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function form(SalesOrder $order): View
    {
        return view('salesorders::orders.form', [
            'order' => $order,
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get()
                ->mapWithKeys(fn (Customer $c) => [$c->uuid => $c->displayName().($c->phone ? ' · '.$c->phone : '')])->all(),
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
            'defaultStore' => $this->stores->default(auth('staff')->user()->business)->uuid,
            'lineOptions' => $this->items->lineOptions(),
        ]);
    }
}
