<?php

namespace Modules\Inventory\Services;

use App\Models\Business\Business;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Store;

class StoreService extends BaseService
{
    /**
     * Stores of the current business, default first.
     *
     * @return Collection<int, Store>
     */
    public function all(bool $activeOnly = false): Collection
    {
        return Store::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * The business's default store, created ("Main store") the first time.
     */
    public function default(Business $business): Store
    {
        $store = Store::query()->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $store) {
            $store = new Store(['name' => config('inventory.default_store_name', 'Main store')]);
            $store->business_id = $business->id;
            $store->is_default = true;
            $store->save();
        } elseif (! $store->is_default) {
            $store->forceFill(['is_default' => true])->save();
        }

        return $store;
    }

    public function create(array $data): array
    {
        $store = new Store($data);
        $store->is_active = true;
        $store->save();

        return $this->success($store, __('inventory::inventory.store_created'));
    }

    public function update(Store $store, array $data): array
    {
        if ($store->is_default && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return $this->failure(__('inventory::inventory.store_default_inactive'));
        }

        $store->fill($data)->save();

        return $this->success($store, __('inventory::inventory.store_updated'));
    }

    public function makeDefault(Store $store): array
    {
        DB::transaction(function () use ($store) {
            Store::query()->where('business_id', $store->business_id)->where('id', '!=', $store->id)
                ->where('is_default', true)->get()->each(fn (Store $other) => $other->forceFill(['is_default' => false])->save());
            $store->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        return $this->success($store, __('inventory::inventory.store_now_default', ['name' => $store->name]));
    }

    /**
     * Only an unused store can be deleted; otherwise make it inactive.
     */
    public function delete(Store $store): array
    {
        if ($store->is_default) {
            return $this->failure(__('inventory::inventory.store_default_delete'));
        }

        if (Movement::query()->where('store_id', $store->id)->exists()) {
            return $this->failure(__('inventory::inventory.store_in_use'));
        }

        StockLevel::query()->where('store_id', $store->id)->delete();
        $store->delete();

        return $this->success(null, __('inventory::inventory.store_deleted'));
    }
}
