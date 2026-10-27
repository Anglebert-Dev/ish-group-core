<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ResetOwnerPasswordRequest;
use App\Http\Requests\SuperAdmin\StoreBusinessRequest;
use App\Http\Requests\SuperAdmin\UpdateBusinessModulesRequest;
use App\Models\Business\Business;
use App\Services\Business\BusinessSetupService;
use App\Services\Module\ModuleManager;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function __construct(
        protected PlatformService $platform,
        protected BusinessSetupService $setup,
        protected ModuleManager $modules,
    ) {
    }

    public function index(): View
    {
        return view('app.super-admin.businesses.index', [
            'businesses' => $this->platform->listBusinesses(),
        ]);
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
            'modules' => $this->modules->catalogue()->where('active', true)->values(),
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

    public function resetOwnerPassword(ResetOwnerPasswordRequest $request, Business $business): RedirectResponse
    {
        $result = $this->platform->resetOwnerPassword($business, $request->validated('password'));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
