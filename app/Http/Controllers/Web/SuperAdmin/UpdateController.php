<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\System\System;
use App\Services\Updates\UpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Self-hosted servers: module updates from our update server
 * (docs/self-hosting-build-guide.md §5.2). Only shown when linked.
 */
class UpdateController extends Controller
{
    public function __construct(protected UpdateService $updates)
    {
    }

    public function index(): View
    {
        abort_unless($this->updates->linked(), 404);

        return view('app.super-admin.updates', [
            'offers' => $this->updates->offers(),
            'history' => $this->updates->history(),
            'mode' => System::getProperty('distribution_update_mode', 'manual'),
            'lastCheck' => System::getProperty('distribution_last_check_at'),
            'lastError' => System::getProperty('distribution_last_error'),
            'writable' => is_writable(base_path('Modules')),
            'version' => config('core.version'),
        ]);
    }

    public function check(): RedirectResponse
    {
        abort_unless($this->updates->linked(), 404);

        $result = $this->updates->check();

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function install(Request $request): RedirectResponse
    {
        abort_unless($this->updates->linked(), 404);

        @set_time_limit(0);
        $modules = $request->input('module') ? [(string) $request->input('module')] : null;
        $result = $this->updates->install($modules);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
