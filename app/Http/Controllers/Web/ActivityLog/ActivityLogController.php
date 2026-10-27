<?php

namespace App\Http\Controllers\Web\ActivityLog;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use App\Models\ActivityLog\ActivityLog;
use App\Repositories\User\UserRepository;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit log of the signed-in user's business.
 */
class ActivityLogController extends Controller
{
    private const FILTERS = ['q', 'action', 'user', 'from', 'to', 'sort', 'dir'];

    public function __construct(protected ActivityLogService $logs)
    {
    }

    public function index(Request $request, UserRepository $users): View
    {
        $filters = $request->only(self::FILTERS);

        return view('app.activity-logs.index', [
            'logs' => $this->logs->list($filters),
            'filters' => $filters,
            'actions' => $this->logs->actionOptions(false),
            'people' => $users->forBusiness(Auth::guard('staff')->user()->business_id)->orderBy('name')->pluck('name', 'id'),
            'exportRoute' => 'activity-logs.export',
            'indexRoute' => 'activity-logs.index',
            'showRoute' => 'activity-logs.show',
            'allBusinesses' => false,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->logs->exportList($request->only(self::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->logs->exportList($request->only(self::FILTERS)));
    }

    public function show(ActivityLog $activityLog): View
    {
        return view('app.activity-logs.show', [
            'log' => $this->logs->details($activityLog),
        ]);
    }
}
