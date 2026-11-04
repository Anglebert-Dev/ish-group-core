<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Services\Module\ModuleDetails;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /app/super-admin/modules — every code module by category, a page for each,
 * and install, update, uninstall and on/off (reference doc §1a "manage-modules").
 */
class ModuleController extends Controller
{
    public function __construct(
        protected ModuleManager $modules,
        protected PermissionService $permissions,
    ) {}

    public function index(): View
    {
        $modules = $this->modules->catalogue()->map(fn (array $module) => $this->withOwnBusiness($module));
        $order = array_flip(ModuleManager::CATEGORIES);

        return view('app.super-admin.modules.index', [
            'modules' => $modules,
            'categories' => $modules->groupBy('category')->sortBy(fn ($group, string $key) => $order[$key] ?? count($order)),
        ]);
    }

    public function show(string $module, ModuleDetails $details): View
    {
        $found = $this->modules->catalogue()->first(fn (array $row) => strtolower($row['name']) === strtolower($module));
        abort_unless($found, 404);

        $found = $this->withOwnBusiness($found);

        return view('app.super-admin.modules.show', [
            'module' => $found,
            'adds' => $details->adds($found['name']),
            'openUrl' => $found['active'] && ! $found['off_here'] ? $details->openUrl($found['name']) : null,
            'businessesUsing' => $found['active'] && $found['billable'] ? Business::query()->get()->filter(fn (Business $b) => $b->canUseModule($found['name']))->count() : null,
            'requirements' => $this->modules->catalogue()->whereIn('name', $found['requires'])->values(),
        ]);
    }

    public function install(string $module): RedirectResponse
    {
        return $this->respond($this->modules->install($module));
    }

    public function update(string $module): RedirectResponse
    {
        return $this->respond($this->modules->update($module));
    }

    public function uninstall(Request $request, string $module): RedirectResponse
    {
        return $this->respond($this->modules->uninstall($module, $request->boolean('purge')));
    }

    public function toggle(Request $request, string $module): RedirectResponse
    {
        return $this->respond($this->modules->toggle($module, $request->boolean('enabled')));
    }

    /** Sold in packages, or free for every business. */
    public function billable(Request $request, string $module): RedirectResponse
    {
        return $this->respond($this->modules->setBillable($module, $request->boolean('billable')));
    }

    /** Running on the platform, but hidden from your own business by its switch? Say so. */
    private function withOwnBusiness(array $module): array
    {
        $business = auth('staff')->user()?->business;

        return $module + ['off_here' => $module['active'] && $business && ! $business->canUseModule($module['name'])];
    }

    private function respond(array $result): RedirectResponse
    {
        if ($result['status']) {
            // New module permissions show up on the role form straight away.
            $this->permissions->initPermissions(true);
        }

        // Back where the button was: the list or the module's own page.
        return redirect()->back(302, [], route('superadmin.modules.index'))
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
