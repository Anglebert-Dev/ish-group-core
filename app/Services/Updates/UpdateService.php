<?php

namespace App\Services\Updates;

use App\Models\System\System;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Module updates on a self-hosted server (docs/self-hosting-build-guide.md §5.2).
 *
 * check(): ask our server what's new; remember the offers and the update
 * mode it gives this site. install(): download, verify and install the
 * offers (all, or the ones named), tell our server how it went.
 *
 * Kept in the system table: distribution_update_mode, distribution_offers,
 * distribution_last_check_at, distribution_last_error, distribution_update_history.
 */
class UpdateService extends BaseService
{
    public const HISTORY = 30;

    public function __construct(
        protected UpdateClient $client,
        protected ModulePackageInstaller $installer,
        protected ModuleManager $modules,
    ) {
    }

    public function linked(): bool
    {
        return $this->client->linked();
    }

    /**
     * @return array{status: bool, data: array{update_mode: string, offers: list<array<string, mixed>>}|null, message: string}
     */
    public function check(): array
    {
        if (! $this->linked()) {
            return $this->failure(__('updates.not_linked'));
        }

        try {
            $answer = $this->client->checkIn($this->report());
        } catch (Throwable $e) {
            System::addProperty('distribution_last_error', $e->getMessage());
            System::addProperty('distribution_last_check_at', now()->toIso8601String());
            report($e);

            return $this->failure($e instanceof UpdateException ? $e->getMessage() : __('updates.check_failed', ['error' => $e->getMessage()]));
        }

        System::addProperty('distribution_update_mode', $answer['update_mode']);
        System::addProperty('distribution_offers', json_encode($answer['offers']));
        System::addProperty('distribution_last_check_at', now()->toIso8601String());
        System::addProperty('distribution_last_error', '');

        return $this->success($answer, $answer['offers'] === []
            ? __('updates.up_to_date')
            : trans_choice('updates.n_available', count($answer['offers']), ['count' => count($answer['offers'])]));
    }

    /**
     * Check, then install when this site is set to automatic (or $force).
     */
    public function run(bool $force = false): array
    {
        $checked = $this->check();

        if (! $checked['status'] || $checked['data']['offers'] === []) {
            return $checked;
        }

        return $force || $checked['data']['update_mode'] === 'auto'
            ? $this->install()
            : $checked;
    }

    /**
     * Install the remembered offers (or only $modules).
     *
     * @param  list<string>|null  $modules
     * @return array{status: bool, data: list<array<string, mixed>>|null, message: string}
     */
    public function install(?array $modules = null): array
    {
        if (! is_writable(base_path('Modules'))) {
            return $this->failure(__('updates.modules_not_writable'));
        }

        $lock = Cache::lock('module-updates', 1800);

        if (! $lock->get()) {
            return $this->failure(__('updates.already_running'));
        }

        try {
            $offers = array_values(array_filter($this->offers(), fn (array $offer) => $modules === null || in_array($offer['module'], $modules, true)));

            if ($offers === []) {
                return $this->failure(__('updates.nothing_to_install'));
            }

            if (config('distribution.backup_before_update', true)) {
                $this->installer->backupDatabase();
            }

            $results = [];
            $failed = [];

            foreach ($offers as $offer) {
                // Something it needs just failed: don't try it.
                if (array_intersect((array) ($offer['requires'] ?? []), $failed) !== []) {
                    $results[] = $this->skipped($offer);
                    $failed[] = $offer['module'];

                    continue;
                }

                $result = $this->installOne($offer);
                $results[] = $result;

                if ($result['status'] !== 'success') {
                    $failed[] = $offer['module'];
                }
            }

            $this->remember($results);

            try {
                $this->client->report($results);
            } catch (Throwable $e) {
                report($e); // the install itself is done; our server learns at the next check-in
            }

            $ok = collect($results)->every(fn (array $result) => $result['status'] === 'success');

            return $ok
                ? $this->success($results, __('updates.installed', ['modules' => collect($results)->map(fn ($r) => $r['module'].' '.$r['to_version'])->implode(', ')]))
                : $this->failure(__('updates.some_failed'), $results);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offers(): array
    {
        $offers = json_decode((string) System::getProperty('distribution_offers', '[]'), true);

        return is_array($offers) ? $offers : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        $history = json_decode((string) System::getProperty('distribution_update_history', '[]'), true);

        return is_array($history) ? $history : [];
    }

    /**
     * What this server tells ours at each check-in.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'core_version' => (string) config('core.version'),
            'php_version' => PHP_VERSION,
            'db_driver' => DB::connection()->getDriverName(),
            'app_url' => (string) config('app.url'),
            'modules_writable' => is_writable(base_path('Modules')),
            'modules' => $this->modules->catalogue()
                ->filter(fn (array $module) => $module['installed'])
                ->map(fn (array $module) => ['name' => $module['name'], 'installed_version' => $module['installed_version'], 'enabled' => $module['enabled']])
                ->values()
                ->all(),
        ];
    }

    private function installOne(array $offer): array
    {
        $zip = storage_path("app/updates/downloads/{$offer['module']}-{$offer['version']}.zip");
        File::ensureDirectoryExists(dirname($zip));

        try {
            $this->client->download($offer, $zip);
            $result = $this->installer->install($offer, $zip);
        } catch (Throwable $e) {
            $result = $this->skipped($offer, 'failed', $e->getMessage());
        } finally {
            File::delete($zip);
        }

        audit(
            'module_update_'.$result['status'],
            "{$result['module']} {$result['to_version']}: ".__('updates.result_'.$result['status']),
            null,
            $result,
            (int) System::getProperty('primary_business_id') ?: null,
        );

        return $result;
    }

    private function skipped(array $offer, string $status = 'failed', ?string $error = null): array
    {
        return [
            'module' => $offer['module'],
            'action' => $offer['action'],
            'from_version' => $offer['from_version'] ?? null,
            'to_version' => $offer['version'],
            'status' => $status,
            'error' => $error ?? __('updates.requirement_failed'),
            'duration_ms' => 0,
        ];
    }

    /**
     * Drop installed offers; keep a short history for the Updates page.
     *
     * @param  list<array<string, mixed>>  $results
     */
    private function remember(array $results): void
    {
        $done = collect($results)->where('status', 'success')->pluck('module')->all();

        System::addProperty('distribution_offers', json_encode(array_values(array_filter($this->offers(), fn (array $offer) => ! in_array($offer['module'], $done, true)))));

        $stamped = array_map(fn (array $result) => $result + ['at' => now()->toIso8601String()], $results);
        System::addProperty('distribution_update_history', json_encode(array_slice([...array_reverse($stamped), ...$this->history()], 0, self::HISTORY)));
    }
}
