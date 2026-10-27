<?php

namespace Modules\Production\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Work orders through the business's stages. Requires Inventory: finished
 * pieces and issued materials move through the stock movement log.
 */
class ProductionServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Production';

    protected string $nameLower = 'production';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
