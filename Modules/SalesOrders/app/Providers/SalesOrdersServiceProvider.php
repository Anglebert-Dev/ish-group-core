<?php

namespace Modules\SalesOrders\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Customer orders. Requires Inventory: confirmed orders reserve stock and
 * delivery takes it out through the movement log.
 */
class SalesOrdersServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'SalesOrders';

    protected string $nameLower = 'salesorders';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
