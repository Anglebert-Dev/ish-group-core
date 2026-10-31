<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Enums\BuildStatus;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\CheckIn;

/**
 * Daily: delete zips past their download window, check-ins older than 90
 * days, and staging folders a crashed build left behind.
 */
class PruneCommand extends Command
{
    public const CHECK_IN_DAYS = 90;

    protected $signature = 'distribution:prune';

    protected $description = 'Delete expired site zips, old check-ins and leftover build folders';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('distribution.disk', 'local'));
        $directory = trim((string) config('distribution.directory', 'distribution'), '/');

        $expired = 0;

        Build::query()->where('status', BuildStatus::Ready)->where('expires_at', '<', now())->each(function (Build $build) use ($disk, &$expired) {
            if ($build->path) {
                $disk->delete($build->path);
            }

            $build->update(['status' => BuildStatus::Expired]);
            $expired++;
        });

        $checkIns = CheckIn::query()->where('created_at', '<', now()->subDays(self::CHECK_IN_DAYS))->delete();

        $leftovers = 0;

        $work = $disk->path($directory.'/work');

        foreach (is_dir($work) ? File::directories($work) : [] as $dir) {
            if (filemtime($dir) < now()->subDay()->getTimestamp()) {
                File::deleteDirectory($dir);
                $leftovers++;
            }
        }

        $this->components->info(__('distribution::distribution.pruned', ['builds' => $expired, 'check_ins' => $checkIns, 'folders' => $leftovers]));

        return self::SUCCESS;
    }
}
