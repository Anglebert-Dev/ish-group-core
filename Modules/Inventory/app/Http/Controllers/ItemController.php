<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Http\Requests\SaveItemRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Services\StoreService;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $items,
        protected StoreService $stores,
        protected StockService $stock,
    ) {
    }

    public function index(Request $request): View
    {
        return view('inventory::items.index', [
            'items' => $this->items->list($request->only(ItemService::FILTERS)),
            'filters' => $request->only(ItemService::FILTERS),
            'categories' => $this->items->categories(),
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->items->exportList($request->only(ItemService::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->items->exportList($request->only(ItemService::FILTERS)));
    }

    public function create(Request $request): View
    {
        $item = new Item(['type' => $request->query('type') === 'material' ? 'material' : 'finished', 'unit' => $this->items->units()[0] ?? 'pcs']);

        return $this->form($item);
    }

    public function store(SaveItemRequest $request): RedirectResponse
    {
        $result = $this->items->create($request->itemData(), $request->variantRows());

        return redirect()->route('inventory.items.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Item $item): View
    {
        $item->load(['variants' => fn ($q) => $q->with('stockLevels')]);
        $stores = $this->stores->all();

        return view('inventory::items.show', [
            'item' => $item,
            'stores' => $stores,
            'activeStores' => $stores->where('is_active', true)->values(),
            'onHand' => $this->stock->itemOnHand($item),
            'reserved' => $this->stock->reserved($item->variants->pluck('id')->all()),
            'movements' => Movement::query()->where('item_id', $item->id)
                ->with(['variant', 'store', 'user'])
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(Item $item): View
    {
        return $this->form($item->load('variants'));
    }

    public function update(SaveItemRequest $request, Item $item): RedirectResponse
    {
        $result = $this->items->update($item, $request->itemData(), $request->variantRows());

        return redirect()->route('inventory.items.show', $item)->with('success', $result['message']);
    }

    public function updateStatus(Request $request, Item $item): RedirectResponse
    {
        $result = $this->items->setActive($item, $request->boolean('active'));

        return back()->with('success', $result['message']);
    }

    public function destroy(Item $item): RedirectResponse
    {
        $result = $this->items->delete($item);

        return $result['status']
            ? redirect()->route('inventory.items.index')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    private function form(Item $item): View
    {
        $business = auth('staff')->user()->business;

        return view('inventory::items.form', [
            'item' => $item,
            'units' => $this->items->units(),
            'categories' => $this->items->categories(),
            'defaultStore' => $this->stores->default($business),
        ]);
    }
}
