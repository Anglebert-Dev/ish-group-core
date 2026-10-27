<?php

namespace Modules\Inventory\Providers;

use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use App\Support\Entitlements\Quota;
use App\Support\Modules\CoreModuleServiceProvider;
use Modules\Inventory\Models\Store;

/**
 * Items, variants, stores and the stock movement log. Modules that move
 * stock require Inventory and call StockService.
 */
class InventoryServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Packages can limit how many stores a business may have.
        $this->app->make(Quota::class)->register('inventory.stores', fn (Business $business) => app(ModuleManager::class)->isInstalled('Inventory')
            ? Store::query()->withoutGlobalScope('business')->where('business_id', $business->id)->count()
            : 0);
    }
}
