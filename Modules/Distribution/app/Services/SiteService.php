<?php

namespace Modules\Distribution\Services;

use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\DB;
use Modules\Distribution\Enums\SiteModuleStatus;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Models\SiteModule;
use Modules\Distribution\Models\SiteToken;
use Modules\Distribution\Support\PlatformAudit;
use Nwidart\Modules\Facades\Module;

/**
 * Customer sites and the modules each one gets. We choose the modules;
 * a module always comes with everything it requires.
 */
class SiteService extends BaseService
{
    public function __construct(protected ModuleManager $modules)
    {
    }

    /**
     * Modules that can be given to a site: every module here except the
     * ones that never ship (Billing, Distribution).
     *
     * @return list<string>
     */
    public function assignableModules(): array
    {
        $neverShip = array_map('strtolower', (array) config('distribution.never_ship', []));

        return collect(Module::all())
            ->map(fn ($module) => $module->getName())
            ->reject(fn (string $name) => in_array(strtolower($name), $neverShip, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, customer_name?: ?string, contact_email?: ?string, app_url?: ?string, update_mode?: string, notes?: ?string}  $data
     * @param  list<string>  $modules
     */
    public function create(array $data, array $modules = []): array
    {
        if ($error = $this->invalidModules($modules)) {
            return $this->failure($error);
        }

        $site = DB::transaction(function () use ($data, $modules) {
            $site = Site::query()->create($data + ['update_mode' => 'manual', 'status' => 'active']);
            $this->addModules($site, $modules);

            return $site;
        });

        return $this->success($site->load('modules'), __('distribution::distribution.site_created'));
    }

    public function update(Site $site, array $data): array
    {
        $site->update($data);

        return $this->success($site, __('distribution::distribution.site_updated'));
    }

    /**
     * Give $modules (and what they require) to the site.
     *
     * @param  list<string>  $modules
     */
    public function assign(Site $site, array $modules): array
    {
        if ($error = $this->invalidModules($modules)) {
            return $this->failure($error);
        }

        $added = DB::transaction(fn () => $this->addModules($site, $modules));

        if ($added !== []) {
            PlatformAudit::log('distribution_modules_assigned', "Modules given to site {$site->name}: ".implode(', ', $added), $site, ['modules' => $added]);
        }

        return $this->success($added, $added === []
            ? __('distribution::distribution.modules_already_assigned')
            : __('distribution::distribution.modules_assigned', ['modules' => implode(', ', $added)]));
    }

    /**
     * Take a module away. Refused while another of the site's modules needs it.
     */
    public function unassign(Site $site, string $module): array
    {
        $row = $site->modules()->where('module', $module)->first();

        if (! $row) {
            return $this->failure(__('body.module_not_found'));
        }

        $neededBy = collect($site->moduleNames())
            ->reject(fn (string $other) => $other === $module)
            ->filter(fn (string $other) => in_array($module, $this->modules->withRequirements([$other]), true))
            ->values()
            ->all();

        if ($neededBy !== []) {
            return $this->failure(__('distribution::distribution.module_needed_by', ['module' => $module, 'modules' => implode(', ', $neededBy)]));
        }

        $row->delete();
        PlatformAudit::log('distribution_module_unassigned', "Module {$module} taken from site {$site->name}", $site, ['module' => $module]);

        return $this->success(null, __('distribution::distribution.module_unassigned', ['module' => $module]));
    }

    /**
     * A new update-API token for the site. Returned once; only its hash is kept.
     */
    public function issueToken(Site $site, ?int $buildId = null): string
    {
        $token = 'ish_'.bin2hex(random_bytes(24));

        $site->tokens()->create([
            'token_hash' => SiteToken::hash($token),
            'last4' => substr($token, -4),
            'build_id' => $buildId,
        ]);

        PlatformAudit::log('distribution_token_issued', "Update token issued for site {$site->name}", $site, ['last4' => substr($token, -4)]);

        return $token;
    }

    public function revokeToken(SiteToken $token): array
    {
        if ($token->isRevoked()) {
            return $this->failure(__('distribution::distribution.token_already_revoked'));
        }

        $token->update(['revoked_at' => now()]);
        PlatformAudit::log('distribution_token_revoked', "Update token …{$token->last4} revoked for site {$token->site->name}", $token->site, ['last4' => $token->last4]);

        return $this->success(null, __('distribution::distribution.token_revoked'));
    }

    /**
     * @param  list<string>  $modules
     * @return list<string> module names actually added
     */
    private function addModules(Site $site, array $modules): array
    {
        $current = $site->moduleNames();
        $added = [];

        foreach ($this->modules->withRequirements($this->canonical($modules)) as $module) {
            if (in_array($module, $current, true)) {
                continue;
            }

            SiteModule::query()->create(['site_id' => $site->id, 'module' => $module, 'status' => SiteModuleStatus::PendingInstall]);
            $added[] = $module;
        }

        return $added;
    }

    /**
     * @param  list<string>  $modules
     */
    private function invalidModules(array $modules): ?string
    {
        $assignable = array_map('strtolower', $this->assignableModules());

        foreach ($this->modules->withRequirements($this->canonical($modules)) as $module) {
            if (! in_array(strtolower($module), $assignable, true)) {
                return __('distribution::distribution.module_not_assignable', ['module' => $module]);
            }
        }

        return null;
    }

    /**
     * Module names as their folders spell them ("savings" → "Savings").
     *
     * @param  list<string>  $modules
     * @return list<string>
     */
    private function canonical(array $modules): array
    {
        return array_values(array_unique(array_map(fn (string $name) => Module::find($name)?->getName() ?? $name, $modules)));
    }
}
