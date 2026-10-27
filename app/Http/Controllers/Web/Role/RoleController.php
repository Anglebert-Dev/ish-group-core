<?php

namespace App\Http\Controllers\Web\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Services\Role\RoleService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roles)
    {
    }

    public function index(): View
    {
        return view('app.roles.index', [
            'roles' => $this->roles->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->roles->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->roles->exportList());
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
        $result = $this->roles->create($request->validated());

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
        $result = $this->roles->update($role, $request->validated());

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
