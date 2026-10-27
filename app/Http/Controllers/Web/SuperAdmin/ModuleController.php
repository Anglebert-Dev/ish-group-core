<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /app/super-admin/modules — install, update, uninstall and switch code
 * modules on or off (reference doc §1a "manage-modules").
 */
class ModuleController extends Controller
{
    public function __construct(
        protected ModuleManager $modules,
        protected PermissionService $permissions,
    ) {
    }

    public function index(): View
    {
        return view('app.super-admin.modules.index', [
            'modules' => $this->modules->catalogue(),
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

    private function respond(array $result): RedirectResponse
    {
        if ($result['status']) {
            // New module permissions show up on the role form straight away.
            $this->permissions->initPermissions(true);
        }

        return redirect()->route('superadmin.modules.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
