<?php

namespace Modules\Delivery\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Delivery notes and part deliveries. Requires SalesOrders.
 */
class DeliveryServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Delivery';

    protected string $nameLower = 'delivery';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
