<?php

namespace Modules\Distribution\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Services\BuildService;

/**
 * Builds a site's zip in the background (Composer can take a minute).
 */
class BuildSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public Build $build)
    {
    }

    public function handle(BuildService $builds): void
    {
        $builds->run($this->build);
    }
}
