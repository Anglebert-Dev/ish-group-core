<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Http\Requests\StockActionRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\StockService;

class StockController extends Controller
{
    public function __construct(protected StockService $stock)
    {
    }

    public function store(StockActionRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();
        $variant = $item->variants()->where('uuid', $data['variant'])->firstOrFail();
        $store = Store::query()->where('uuid', $data['store'])->where('is_active', true)->firstOrFail();
        $quantity = (float) $data['quantity'];
        $reason = $data['reason'] ?? null;

        try {
            $movements = match ($data['action']) {
                'receive' => $this->stock->receive($variant, $store, $quantity, $reason, isset($data['unit_cost']) ? (float) $data['unit_cost'] : null),
                'issue' => $this->stock->issue($variant, $store, $quantity, $reason),
                'adjust' => $this->stock->adjustTo($variant, $store, $quantity, (string) $reason),
                'transfer' => $this->stock->transfer(
                    $variant,
                    $store,
                    Store::query()->where('uuid', $data['to_store'])->where('is_active', true)->firstOrFail(),
                    $quantity,
                    $reason,
                ),
            };
        } catch (InsufficientStock $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $movements->isEmpty()
            ? __('inventory::inventory.stock_unchanged')
            : __('inventory::inventory.stock_saved'));
    }
}
