<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePlatformSettingsRequest;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformSettingController extends Controller
{
    public function __construct(protected PlatformService $platform)
    {
    }

    public function index(): View
    {
        return view('app.super-admin.settings', [
            'settings' => $this->platform->settings(),
            'system' => $this->platform->systemInfo(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        $result = $this->platform->updateSettings($request->validated());

        return redirect()->route('superadmin.settings.index', ['tab' => 'registration'])->with('success', $result['message']);
    }
}
