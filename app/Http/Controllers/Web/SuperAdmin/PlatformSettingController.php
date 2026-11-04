<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateMailSettingsRequest;
use App\Http\Requests\SuperAdmin\UpdatePlatformSettingsRequest;
use App\Services\Mail\MailSettings;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformSettingController extends Controller
{
    public function __construct(protected PlatformService $platform)
    {
    }

    public function index(MailSettings $mail): View
    {
        return view('app.super-admin.settings', [
            'settings' => $this->platform->settings(),
            'system' => $this->platform->systemInfo(),
            'mail' => $mail->form(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        $result = $this->platform->updateSettings($request->validated());

        return redirect()->route('superadmin.settings.index', ['tab' => $request->validated('tab') ?? 'registration'])->with('success', $result['message']);
    }

    public function updateMail(UpdateMailSettingsRequest $request, MailSettings $mail): RedirectResponse
    {
        $data = $request->validated();
        $result = $mail->save($data, $data['action'] === 'test' ? $data['test_to'] : null);
        $back = redirect()->route('superadmin.settings.index', ['tab' => 'email']);

        return $result['status']
            ? $back->with('success', $result['message'])
            : $back->withInput($request->except('password'))->with('error', $result['message']);
    }

    public function resetMail(MailSettings $mail): RedirectResponse
    {
        return redirect()->route('superadmin.settings.index', ['tab' => 'email'])->with('success', $mail->reset()['message']);
    }
}
