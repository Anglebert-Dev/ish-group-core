<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\StoreService;

class StoreController extends Controller
{
    public function __construct(protected StoreService $stores)
    {
    }

    public function index(): View
    {
        $this->stores->default(auth('staff')->user()->business);

        return view('inventory::stores.index', [
            'stores' => $this->stores->all(),
            'itemCounts' => StockLevel::query()->where('quantity', '>', 0)
                ->selectRaw('store_id, COUNT(*) as total')->groupBy('store_id')->pluck('total', 'store_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->stores->create($this->validated($request));

        return back()->with('success', $result['message']);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $result = $this->stores->update($store, $this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function makeDefault(Store $store): RedirectResponse
    {
        return back()->with('success', $this->stores->makeDefault($store)['message']);
    }

    public function destroy(Store $store): RedirectResponse
    {
        $result = $this->stores->delete($store);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
