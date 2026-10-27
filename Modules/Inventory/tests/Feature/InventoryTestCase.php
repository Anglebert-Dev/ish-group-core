<?php

namespace Modules\Inventory\Tests\Feature;

use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\StoreService;
use Tests\TestCase;

abstract class InventoryTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Inventory');
    }

    protected function store(Business $business, string $name = 'Second store'): Store
    {
        $store = new Store(['name' => $name]);
        $store->business_id = $business->id;
        $store->save();

        return $store;
    }

    protected function defaultStore(Business $business): Store
    {
        return app(StoreService::class)->default($business);
    }

    /**
     * An item with one variant per given option ("M", "L"), or a single plain variant.
     *
     * @return array{0: Item, 1: list<Variant>}
     */
    protected function item(Business $business, array $options = [], array $overrides = []): array
    {
        $item = new Item($overrides + ['type' => 'finished', 'name' => 'School shirt', 'unit' => 'pcs', 'option1_name' => $options ? 'Size' : null]);
        $item->business_id = $business->id;
        $item->save();

        $variants = [];
        foreach ($options ?: [null] as $option) {
            $variant = new Variant(['option1' => $option, 'sale_price' => 5000]);
            $variant->business_id = $business->id;
            $variant->item_id = $item->id;
            $variant->save();
            $variants[] = $variant->fresh('item');
        }

        return [$item, $variants];
    }
}
