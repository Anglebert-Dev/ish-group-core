<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\BuildService;

/**
 *   php artisan distribution:build "Kigali Finance"     (site name or uuid)
 *   php artisan distribution:build "Kigali Finance" --standalone --developer
 *
 * Builds the zip right here (no queue) and prints the install code.
 */
class BuildSiteCommand extends Command
{
    protected $signature = 'distribution:build {site : Site name or uuid} {--developer : Keep the tests, guides and developer packages} {--standalone : Hand it over for good: no link to this server, no updates}';

    protected $description = 'Build the ready-to-extract zip for a self-hosted site';

    public function handle(BuildService $builds): int
    {
        $site = Site::query()->where('uuid', $this->argument('site'))->orWhere('name', $this->argument('site'))->first();

        if (! $site) {
            $this->components->error(__('distribution::distribution.site_not_found'));

            return self::FAILURE;
        }

        $started = $builds->start($site, (bool) $this->option('developer'), (bool) $this->option('standalone'));

        if (! $started['status']) {
            $this->components->error($started['message']);

            return self::FAILURE;
        }

        $this->components->info(__('distribution::distribution.building', ['site' => $site->name, 'modules' => implode(', ', $site->moduleNames())]));
        $result = $builds->run($started['data']['build']);

        if (! $result['status']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $build = $result['data'];
        $this->components->twoColumnDetail(__('distribution::distribution.build_file'), $builds->absolutePath($build));
        $this->components->twoColumnDetail(__('distribution::distribution.install_code'), $started['data']['code']);
        $this->components->warn(__('distribution::distribution.install_code_once'));

        return self::SUCCESS;
    }
}
