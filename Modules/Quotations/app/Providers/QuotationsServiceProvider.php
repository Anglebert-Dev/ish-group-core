<?php

namespace Modules\Quotations\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Quotes and samples. Requires SalesOrders: an accepted quote converts into an order.
 */
class QuotationsServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Quotations';

    protected string $nameLower = 'quotations';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
