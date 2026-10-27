<?php

namespace App\Http\Controllers\Web\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog\ActivityLog;
use App\Services\ActivityLog\ActivityLogService;
use App\Libraries\ListExportLibrary;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $logs)
    {
    }

    public function index(): View
    {
        return view('app.activity-logs.index', [
            'logs' => $this->logs->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->logs->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->logs->exportList());
    }

    public function show(ActivityLog $activityLog): View
    {
        return view('app.activity-logs.show', [
            'log' => $this->logs->details($activityLog),
        ]);
    }
}
