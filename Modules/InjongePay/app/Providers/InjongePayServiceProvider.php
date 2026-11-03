<?php

namespace Modules\InjongePay\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

/**
 * Mobile-money collection through GTBank PaymentBridge. Partner integrations
 * (POS/EBM APIs) live in the InjongePayConnect module.
 */
class InjongePayServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'InjongePay';

    protected string $nameLower = 'injongepay';

    protected array $commands = [];

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
