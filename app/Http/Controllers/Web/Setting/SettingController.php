<?php

namespace App\Http\Controllers\Web\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Services\Setting\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settings)
    {
    }

    public function index(): View
    {
        return view('app.settings.index', [
            'setting' => $this->settings->current(),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $result = $this->settings->update(
            $this->settings->current(),
            $request->safe()->except(['logo', 'favicon']),
            $request->file('logo'),
            $request->file('favicon'),
        );

        return redirect()->route('settings.index')->with(
            $result['status'] ? 'success' : 'error',
            $result['message']
        );
    }
}
