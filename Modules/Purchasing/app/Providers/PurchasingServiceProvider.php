<?php

namespace Modules\Purchasing\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Suppliers, purchase orders and goods received. Requires Inventory:
 * receiving puts stock in through the movement log.
 */
class PurchasingServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Purchasing';

    protected string $nameLower = 'purchasing';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
