<?php

namespace Modules\Reports\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

class ReportsServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Reports';

    protected string $nameLower = 'reports';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
