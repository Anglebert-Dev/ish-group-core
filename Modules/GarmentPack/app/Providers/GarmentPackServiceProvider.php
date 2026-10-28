<?php

namespace Modules\GarmentPack\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Garment pack: style library, bill of materials and costing on top of
 * Production and Inventory.
 */
class GarmentPackServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'GarmentPack';

    protected string $nameLower = 'garmentpack';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
