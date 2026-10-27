<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ResetUserPasswordRequest;
use App\Http\Requests\SuperAdmin\StoreBusinessRequest;
use App\Http\Requests\SuperAdmin\UpdateBusinessModulesRequest;
use App\Libraries\ListExportLibrary;
use App\Models\Business\Business;
use App\Models\User;
use App\Services\Business\BusinessSetupService;
use App\Services\Module\ModuleManager;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BusinessController extends Controller
{
    public function __construct(
        protected PlatformService $platform,
        protected BusinessSetupService $setup,
        protected ModuleManager $modules,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status']);

        return view('app.super-admin.businesses.index', [
            'businesses' => $this->platform->listBusinesses($filters),
            'filters' => $filters,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->platform->exportBusinesses($request->only(['q', 'status'])));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->platform->exportBusinesses($request->only(['q', 'status'])));
    }

    public function create(): View
    {
        return view('app.super-admin.businesses.create');
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $result = $this->setup->createWithOwner(
            ['name' => $data['business_name'], 'is_active' => $request->boolean('is_active', true)],
            ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']],
        );

        if (! $result['status']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('superadmin.businesses.show', $result['data']['business'])
            ->with('success', $result['message']);
    }

    public function show(Business $business): View
    {
        return view('app.super-admin.businesses.show', [
            'business' => $business->load('owner'),
            'users' => $this->platform->businessUsers($business),
            'stats' => $this->platform->businessStats($business),
            'modules' => $this->modules->catalogue()->where('active', true)
                ->reject(fn ($m) => $this->modules->isBillingExempt($m['name']))->values(),
            'modulesFromPackage' => app(\App\Contracts\Entitlements::class)->controlsModules(),
        ]);
    }

    public function toggleActive(Request $request, Business $business): RedirectResponse
    {
        $result = $this->platform->toggleActive(
            $business,
            $request->boolean('is_active'),
            (int) Auth::guard('staff')->user()->business_id,
        );

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function updateModules(UpdateBusinessModulesRequest $request, Business $business): RedirectResponse
    {
        $result = $this->platform->updateEnabledModules($business, $request->validated('modules', []));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * {user} is a UUID across all businesses (the normal {user} binding is
     * limited to the superadmin's own business).
     */
    public function resetUserPassword(ResetUserPasswordRequest $request, string $user): RedirectResponse
    {
        $target = User::query()->where('uuid', $user)->firstOrFail();
        $result = $this->platform->resetUserPassword($target, $request->validated('password'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, Business $business): RedirectResponse
    {
        $result = $this->platform->deleteBusiness(
            $business,
            Auth::guard('staff')->user(),
            (string) $request->input('confirm_name'),
        );

        return $result['status']
            ? redirect()->route('superadmin.businesses.index')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }
}
