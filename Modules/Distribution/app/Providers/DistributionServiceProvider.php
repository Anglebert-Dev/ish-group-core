<?php

namespace Modules\Distribution\Providers;

use App\Support\Modules\CoreModuleServiceProvider;
use Modules\Distribution\Console\KeygenCommand;
use Modules\Distribution\Console\PublishReleaseCommand;

/**
 * Self-hosting, our side (docs/self-hosting-build-guide.md §6): sites,
 * module assignment, signed releases. Never shipped to a customer.
 */
class DistributionServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Distribution';

    protected string $nameLower = 'distribution';

    protected array $commands = [
        KeygenCommand::class,
        PublishReleaseCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
