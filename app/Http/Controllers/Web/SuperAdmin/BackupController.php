<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateBackup;
use App\Models\System\System;
use App\Services\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backups)
    {
    }

    public function index(): View
    {
        return view('app.super-admin.backups', [
            'backups' => $this->backups->list(),
            'daily' => System::getProperty('backup_daily', '0') === '1',
            'keep' => (int) System::getProperty('backup_keep', 7),
            'lastAt' => System::getProperty('backup_last_at'),
        ]);
    }

    public function store(): RedirectResponse
    {
        CreateBackup::dispatch(Auth::guard('staff')->id());

        return back()->with('success', __('body.backup_started'));
    }

    public function download(string $backup): BinaryFileResponse
    {
        $path = $this->backups->path($backup);
        abort_unless($path, 404);

        return response()->download($path);
    }

    public function destroy(string $backup): RedirectResponse
    {
        abort_unless($this->backups->delete($backup), 404);

        return back()->with('success', __('body.backup_deleted'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_daily' => ['boolean'],
            'backup_keep' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        System::addProperty('backup_daily', $request->boolean('backup_daily') ? '1' : '0');
        System::addProperty('backup_keep', (string) $data['backup_keep']);

        return back()->with('success', __('body.settings_updated'));
    }
}
