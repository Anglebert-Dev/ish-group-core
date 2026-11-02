<?php

namespace App\Console\Commands;

use App\Services\Permission\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Run by apply-core.php in the NEW core right after its files are in place
 * (docs/self-hosting-build-guide.md §5.3): the core's migrations, then fresh
 * permissions and caches. If a migration fails, its migrations are undone
 * here (they live in this new code) and the command fails, so the script
 * puts the old code back.
 */
class AfterCoreUpdateCommand extends Command
{
    protected $signature = 'core:after-update';

    protected $description = 'Finish a core update (run by the updater, not by hand)';

    protected $hidden = true;

    public function handle(PermissionService $permissions): int
    {
        $batch = (int) DB::table('migrations')->max('batch');
        $recorded = DB::table('migrations')->pluck('migration')->all();

        try {
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException(trim(Artisan::output()));
            }
        } catch (Throwable $e) {
            $this->undo($batch, $recorded);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $permissions->initPermissions(true);
        Artisan::call('optimize:clear');
        $this->info('Core update finished.');

        return self::SUCCESS;
    }

    /**
     * Roll back the migrations this update ran, then the one that failed half-way
     * (never recorded, so rollback skips it; on MySQL its changes stay otherwise).
     *
     * @param  list<string>  $recorded  migrations recorded before this update
     */
    private function undo(int $batch, array $recorded): void
    {
        try {
            // Found before rolling back, which un-records the ones that worked.
            $now = DB::table('migrations')->pluck('migration')->all();
            $failed = collect(File::files(database_path('migrations')))
                ->map(fn ($file) => $file->getFilenameWithoutExtension())
                ->reject(fn (string $name) => in_array($name, $recorded, true) || in_array($name, $now, true))
                ->sort()
                ->first();

            if ((int) DB::table('migrations')->max('batch') > $batch) {
                Artisan::call('migrate:rollback', ['--force' => true]);
            }

            if ($failed) {
                $migration = require database_path("migrations/{$failed}.php");

                if (is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
