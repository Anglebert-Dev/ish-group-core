<?php

namespace Modules\Savings\Providers;

use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Savings\Console\SendAlertsCommand;

/**
 * Personal finance. Independent: needs no other module.
 */
class SavingsServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Savings';

    protected string $nameLower = 'savings';

    protected array $commands = [
        SendAlertsCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->scoped(\Modules\Savings\Support\CurrentOwner::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('savings:alerts')->dailyAt('06:00')->withoutOverlapping();
    }
}
