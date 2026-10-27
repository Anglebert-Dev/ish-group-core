<?php

namespace App\Http\Controllers\Web\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Services\Role\RoleService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Models\Role\Role;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roles)
    {
    }

    public function index(): View
    {
        return view('app.roles.index', [
            'roles' => $this->roles->list(Auth::guard('staff')->user()),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->roles->exportList(Auth::guard('staff')->user()));
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->roles->exportList(Auth::guard('staff')->user()));
    }

    public function create(): View
    {
        return view('app.roles.create', [
            'catalogue' => $this->roles->permissionCatalogue(),
            'assigned' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $result = $this->roles->create(Auth::guard('staff')->user(), $request->validated());

        if (! $result['status']) {
            return back()->withInput()->withErrors(['name' => $result['message']]);
        }

        return redirect()->route('roles.index')->with('success', $result['message']);
    }

    public function show(Role $role): View
    {
        return view('app.roles.show', [
            'role' => $role,
            'catalogue' => $this->roles->permissionCatalogue(),
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function edit(Role $role): View
    {
        return view('app.roles.edit', [
            'role' => $role,
            'catalogue' => $this->roles->permissionCatalogue(),
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $result = $this->roles->update(Auth::guard('staff')->user(), $role, $request->validated());

        return redirect()->route('roles.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $result = $this->roles->delete($role);

        return redirect()->route('roles.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
