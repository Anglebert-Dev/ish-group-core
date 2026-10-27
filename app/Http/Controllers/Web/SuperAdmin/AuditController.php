<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Enums\ActivityLogAction;
use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-wide audit log: every business plus platform events.
 */
class AuditController extends Controller
{
    private const FILTERS = ['q', 'action', 'business', 'from', 'to'];

    public function __construct(protected ActivityLogService $logs)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTERS);

        return view('app.activity-logs.index', [
            'logs' => $this->logs->list($filters, true),
            'filters' => $filters,
            'actions' => ActivityLogAction::options(),
            'businesses' => ['platform' => __('body.audit_platform')] + Business::query()->orderBy('name')->pluck('name', 'id')->all(),
            'exportRoute' => 'superadmin.audit.export',
            'indexRoute' => 'superadmin.audit.index',
            'showRoute' => 'superadmin.audit.show',
            'allBusinesses' => true,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $export = $this->logs->exportList($request->only(self::FILTERS), true);

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    public function show(string $log): View
    {
        $entry = ActivityLog::query()->withoutGlobalScope('business')->where('uuid', $log)->firstOrFail();

        return view('app.activity-logs.show', [
            'log' => $this->logs->details($entry),
            'backUrl' => route('superadmin.audit.index'),
        ]);
    }
}
