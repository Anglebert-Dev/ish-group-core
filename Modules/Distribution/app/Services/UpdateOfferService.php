<?php

namespace Modules\Distribution\Services;

use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\DB;
use Modules\Distribution\Enums\SiteModuleStatus;
use Modules\Distribution\Models\CheckIn;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\SiteModule;
use Modules\Distribution\Models\UpdateReport;
use Modules\Distribution\Support\PlatformAudit;

/**
 * Server side of the update link (docs/self-hosting-build-guide.md §7):
 * what a site reported, what it is offered, what it says happened.
 */
class UpdateOfferService
{
    /** How often sites are asked to check (minutes). */
    public const POLL_MINUTES = 60;

    public function __construct(protected ModuleManager $modules)
    {
    }

    /**
     * Record a check-in and work out the offers.
     *
     * @param  array{core_version?: ?string, php_version?: ?string, db_driver?: ?string, app_url?: ?string, modules_writable?: bool, modules?: list<array{name: string, installed_version?: ?string}>}  $reported
     * @return list<array<string, mixed>>
     */
    public function checkIn(Site $site, array $reported, ?string $ip): array
    {
        return DB::transaction(function () use ($site, $reported, $ip) {
            $site->forceFill(array_filter([
                'core_version' => $reported['core_version'] ?? null,
                'php_version' => $reported['php_version'] ?? null,
                'db_driver' => $reported['db_driver'] ?? null,
                'app_url' => $site->app_url ?: ($reported['app_url'] ?? null),
            ], fn ($value) => $value !== null) + [
                'modules_writable' => (bool) ($reported['modules_writable'] ?? false),
                'last_check_in_at' => now(),
                'last_ip' => $ip,
                'installed_at' => $site->installed_at ?? now(),
            ])->saveQuietly();

            $installed = collect($reported['modules'] ?? [])
                ->filter(fn ($module) => is_array($module) && filled($module['name'] ?? null))
                ->mapWithKeys(fn ($module) => [strtolower($module['name']) => $module['installed_version'] ?? null])
                ->all();

            $offers = $this->offers($site, $installed);

            CheckIn::query()->create([
                'site_id' => $site->id,
                'ip' => $ip,
                'core_version' => $reported['core_version'] ?? null,
                'reported' => $reported,
                'offered' => array_map(fn ($offer) => $offer['module'].' '.$offer['version'], $offers),
            ]);

            return $offers;
        });
    }

    /**
     * Assigned modules whose newest available release is newer than what the
     * site has (or that it doesn't have yet), requirements first.
     *
     * @param  array<string, ?string>  $installed  lower-case name => version
     * @return list<array<string, mixed>>
     */
    public function offers(Site $site, array $installed): array
    {
        $rows = $site->modules()->get()->keyBy('module');
        $latest = Release::latestFor($rows->keys()->all());
        $offers = [];

        foreach ($this->installOrder($rows->keys()->all()) as $name) {
            /** @var SiteModule $row */
            $row = $rows[$name];
            $release = $latest[$name] ?? null;
            $current = $installed[strtolower($name)] ?? null;

            $row->forceFill([
                'installed_version' => $current,
                'last_reported_at' => now(),
                'status' => $this->statusFor($row, $current, $release),
            ])->save();

            if (! $release || ($current !== null && ! version_compare($release->version, $current, '>'))) {
                continue;
            }

            $offers[] = [
                'module' => $name,
                'action' => $current === null ? 'install' : 'update',
                'from_version' => $current,
                'version' => $release->version,
                'requires' => array_values((array) $release->requires),
                'changelog' => $release->changelog,
                'size' => $release->size,
                'sha256' => $release->sha256,
                'signature' => $release->signature,
                'download_url' => route('distribution.api.download', $release),
            ];
        }

        return $offers;
    }

    /**
     * @param  list<array{module: string, action: string, from_version?: ?string, to_version: string, status: string, error?: ?string, duration_ms?: ?int}>  $results
     */
    public function report(Site $site, array $results): void
    {
        foreach ($results as $result) {
            UpdateReport::query()->create([
                'site_id' => $site->id,
                'module' => $result['module'],
                'action' => $result['action'],
                'from_version' => $result['from_version'] ?? null,
                'to_version' => $result['to_version'],
                'status' => $result['status'],
                'error' => $result['error'] ?? null,
                'duration_ms' => $result['duration_ms'] ?? null,
            ]);

            $row = $site->modules()->where('module', $result['module'])->first();

            if ($row) {
                $ok = $result['status'] === 'success';
                $row->forceFill([
                    'installed_version' => $ok ? $result['to_version'] : $row->installed_version,
                    'status' => $ok ? SiteModuleStatus::Installed : SiteModuleStatus::Failed,
                    'last_error' => $ok ? null : ($result['error'] ?? $result['status']),
                    'last_reported_at' => now(),
                ])->save();
            }

            PlatformAudit::log(
                'distribution_update_reported',
                "{$site->name}: {$result['module']} {$result['to_version']} {$result['status']}",
                $site,
                $result,
            );
        }
    }

    private function statusFor(SiteModule $row, ?string $current, ?Release $release): SiteModuleStatus
    {
        if ($current === null) {
            return $row->status === SiteModuleStatus::Failed ? SiteModuleStatus::Failed : SiteModuleStatus::PendingInstall;
        }

        if ($release && version_compare($release->version, $current, '>')) {
            // A failed attempt at this version stays visible until it succeeds.
            return $row->status === SiteModuleStatus::Failed && $row->last_error ? SiteModuleStatus::Failed : SiteModuleStatus::UpdateAvailable;
        }

        return SiteModuleStatus::Installed;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function installOrder(array $names): array
    {
        $ordered = [];

        $visit = function (string $name, array $path) use (&$visit, &$ordered, $names) {
            if (in_array($name, $ordered, true) || in_array($name, $path, true)) {
                return;
            }

            foreach ($this->modules->requires($name) as $required) {
                if (in_array($required, $names, true)) {
                    $visit($required, [...$path, $name]);
                }
            }

            $ordered[] = $name;
        };

        foreach ($names as $name) {
            $visit($name, []);
        }

        return $ordered;
    }
}
