<?php

namespace Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleManager;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Enums\ItemType;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StoreService;
use Modules\Inventory\Support\DocumentLines;
use Modules\Production\Models\WorkOrder;
use Modules\Production\Services\WorkOrderService;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrders,
        protected ItemService $items,
        protected StoreService $stores,
        protected ModuleManager $modules,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(WorkOrderService::FILTERS) + ['status' => 'open'];

        return view('production::work-orders.index', [
            'workOrders' => $this->workOrders->list($filters),
            'filters' => $filters,
            'lines' => $this->workOrders->lines(),
            'today' => $this->workOrders->today(),
        ]);
    }

    public function create(Request $request): View
    {
        $workOrder = new WorkOrder(['start_date' => now()]);

        if ($request->filled('sales_order') && $this->salesOrdersActive()) {
            $salesOrder = \Modules\SalesOrders\Models\SalesOrder::query()->where('uuid', $request->query('sales_order'))->with('lines.variant')->first();
            $line = $salesOrder?->lines->firstWhere('variant.uuid', $request->query('variant')) ?? $salesOrder?->lines->first();
            $workOrder->fill([
                'sales_order_id' => $salesOrder?->id,
                'variant_id' => $line?->variant_id,
                'quantity' => $line?->quantity,
                'due_date' => $salesOrder?->due_date,
            ]);
        }

        return $this->form($workOrder);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->workOrders->create($this->validated($request));

        return redirect()->route('production.work-orders.show', $result['data'])->with('success', $result['message']);
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['variant.item', 'store', 'entries.user']);

        return view('production::work-orders.show', [
            'workOrder' => $workOrder,
            'salesOrder' => $workOrder->sales_order_id && $this->salesOrdersActive()
                ? \Modules\SalesOrders\Models\SalesOrder::query()->find($workOrder->sales_order_id)
                : null,
            'materials' => Movement::query()
                ->where('reference_type', $workOrder->getMorphClass())->where('reference_id', $workOrder->id)
                ->where('type', 'production_out')->with(['variant.item', 'store'])->latest('id')->get(),
            'materialOptions' => $this->items->lineOptions(ItemType::Material),
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
        ]);
    }

    public function edit(WorkOrder $workOrder): View
    {
        return $this->form($workOrder);
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $result = $this->workOrders->update($workOrder, $this->validated($request));

        return $result['status']
            ? redirect()->route('production.work-orders.show', $workOrder)->with('success', $result['message'])
            : back()->withInput()->with('error', $result['message']);
    }

    public function record(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'integer', 'min:0', 'max:'.$workOrder->lastStage()],
            'quantity' => ['required', 'numeric', 'not_in:0', 'min:-999999999', 'max:999999999'],
            'work_date' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->workOrders->record($workOrder, (int) $data['stage'], (float) $data['quantity'], $data['work_date'] ?? null, $data['note'] ?? null);

        return back()->withInput($result['status'] ? [] : $request->input())->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function complete(WorkOrder $workOrder): RedirectResponse
    {
        $result = $this->workOrders->complete($workOrder);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->workOrders->cancel($workOrder, $request->input('reason'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function materials(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $request->validate(DocumentLines::rules('materials') + ['store' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:500']]);
        $store = Store::query()->where('uuid', $request->input('store'))->where('is_active', true)->firstOrFail();
        $lines = array_map(fn ($line) => ['variant' => $line['variant'], 'quantity' => $line['quantity']], DocumentLines::resolve((array) $request->input('materials'), 'materials'));

        $result = $this->workOrders->issueMaterials($workOrder, $store, $lines, $request->input('note'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function settings(): View
    {
        return view('production::settings', ['stages' => $this->workOrders->stages()]);
    }

    public function saveSettings(Request $request, BusinessSettings $settings): RedirectResponse
    {
        $request->validate(['stages' => ['required', 'string', 'max:1000']]);
        $stages = collect(preg_split('/\R/', (string) $request->input('stages')))
            ->map(fn ($stage) => mb_substr(trim($stage), 0, 60))->filter()->unique()->values()->take(12)->all();

        if ($stages === []) {
            return back()->withErrors(['stages' => __('validation.required', ['attribute' => __('production::production.stages')])]);
        }

        $settings->set('production.stages', $stages);

        return back()->with('success', __('production::production.settings_saved'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'variant' => ['required', 'string'],
            'store' => ['required', 'string'],
            'sales_order' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'line' => ['nullable', 'string', 'max:60'],
            'daily_target' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $variant = Variant::query()->where('uuid', $data['variant'])->firstOrFail();
        $store = Store::query()->where('uuid', $data['store'])->where('is_active', true)->firstOrFail();
        $salesOrderId = filled($data['sales_order'] ?? null) && $this->salesOrdersActive()
            ? \Modules\SalesOrders\Models\SalesOrder::query()->where('uuid', $data['sales_order'])->value('id')
            : null;

        return [
            'variant_id' => $variant->id,
            'store_id' => $store->id,
            'sales_order_id' => $salesOrderId,
            'quantity' => (float) $data['quantity'],
            'line' => $data['line'] ?? null,
            'daily_target' => $data['daily_target'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function form(WorkOrder $workOrder): View
    {
        $salesOrders = $this->salesOrdersActive()
            ? \Modules\SalesOrders\Models\SalesOrder::query()->with('customer')
                ->whereIn('status', ['pending', 'confirmed', 'in_production', 'ready'])
                ->latest('id')->limit(200)->get()
                ->mapWithKeys(fn ($o) => [$o->uuid => $o->number.' · '.$o->customer?->displayName()])->all()
            : null;

        return view('production::work-orders.form', [
            'workOrder' => $workOrder,
            'products' => collect($this->items->lineOptions(ItemType::Finished))->mapWithKeys(fn ($o) => [$o['uuid'] => $o['label']])->all(),
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
            'defaultStore' => $this->stores->default(auth('staff')->user()->business)->uuid,
            'salesOrders' => $salesOrders,
            'selectedSalesOrder' => $workOrder->sales_order_id && $this->salesOrdersActive()
                ? \Modules\SalesOrders\Models\SalesOrder::query()->whereKey($workOrder->sales_order_id)->value('uuid')
                : null,
            'lines' => $this->workOrders->lines(),
            'stages' => $workOrder->exists ? $workOrder->stages : $this->workOrders->stages(),
        ]);
    }

    private function salesOrdersActive(): bool
    {
        return $this->modules->isActive('SalesOrders') && (bool) auth('staff')->user()?->business?->canUseModule('SalesOrders');
    }
}
