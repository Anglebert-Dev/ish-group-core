<?php

namespace Modules\Invoicing\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Sales invoices and customer payments. Requires Inventory: invoices take
 * goods out of stock through the movement log.
 */
class InvoicingServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Invoicing';

    protected string $nameLower = 'invoicing';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
