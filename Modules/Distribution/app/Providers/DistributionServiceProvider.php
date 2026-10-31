<?php

namespace Modules\Distribution\Providers;

use App\Support\Modules\CoreModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Distribution\Console\BuildSiteCommand;
use Modules\Distribution\Console\KeygenCommand;
use Modules\Distribution\Console\PruneCommand;
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
        BuildSiteCommand::class,
        KeygenCommand::class,
        PruneCommand::class,
        PublishReleaseCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Per token: a site checks hourly, so this only stops runaway clients.
        RateLimiter::for('distribution-api', fn (Request $request) => Limit::perMinute(30)->by(sha1((string) $request->bearerToken()).'|'.$request->ip()));
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('distribution:prune')->dailyAt('03:30')->withoutOverlapping();
    }
}
