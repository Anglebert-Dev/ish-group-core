<?php

namespace Modules\InjongePayConnect\Providers;

use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Partner integrations for Injonge Pay. Each integrator speaks one contract
 * (config injongepayconnect.profiles); the money stays in InjongePay.
 */
class InjongePayConnectServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'InjongePayConnect';

    protected string $nameLower = 'injongepayconnect';

    protected array $commands = [
        \Modules\InjongePayConnect\Console\PruneTokensCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('injongepayconnect:prune-tokens')->dailyAt('03:00')->withoutOverlapping();
    }
}
