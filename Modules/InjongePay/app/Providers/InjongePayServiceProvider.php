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

    protected array $commands = [
        \Modules\InjongePay\Console\SyncMerchantsCommand::class,
        \Modules\InjongePay\Console\ProcessInboxCommand::class,
        \Modules\InjongePay\Console\SweepFeesCommand::class,
        \Modules\InjongePay\Console\MirrorCommand::class,
        \Modules\InjongePay\Console\CloseDayCommand::class,
        \Modules\InjongePay\Console\CheckCashoutsCommand::class,
        \Modules\InjongePay\Console\ExpireApprovalsCommand::class,
        \Modules\InjongePay\Console\ImportLegacyCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('injongepay:sync-merchants')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('injongepay:sync-merchants --all')->dailyAt('02:00')->withoutOverlapping();
        $schedule->command('injongepay:process-inbox')->everyMinute()->withoutOverlapping();
        $schedule->command('injongepay:sweep-fees')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('injongepay:mirror')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('injongepay:check-cashouts')->everyMinute()->withoutOverlapping();
        $schedule->command('injongepay:expire-approvals')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('injongepay:close-day')->dailyAt('01:30')->withoutOverlapping();
    }

    public function register(): void
    {
        parent::register();

        // Every GTBank call also goes to storage/logs/gtbank-YYYY-MM-DD.log, kept 90 days.
        config(['logging.channels.gtbank' => config('logging.channels.gtbank') ?? [
            'driver' => 'daily',
            'path' => storage_path('logs/gtbank.log'),
            'level' => 'info',
            'days' => 90,
        ]]);
    }
}
