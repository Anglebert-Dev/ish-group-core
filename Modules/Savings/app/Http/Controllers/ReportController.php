<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Savings\Services\Periods;
use Modules\Savings\Services\Reports;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(protected Reports $reports, protected Periods $periods)
    {
    }

    public function index(Request $request): View
    {
        [$report, $from, $to] = $this->params($request);

        return view('savings::reports.index', ['report' => $report, 'from' => $from, 'to' => $to, 'result' => $this->reports->run($report, $from, $to)]);
    }

    public function export(Request $request, string $format): Response
    {
        [$report, $from, $to] = $this->params($request);
        $table = $this->reports->run($report, $from, $to)['table'];
        $table->title .= ' · '.$from.' – '.$to;
        audit(\App\Enums\ActivityLogAction::Exported, __('savings::savings.report_'.$report));

        return $format === 'pdf' ? ListExportLibrary::pdf($table) : ListExportLibrary::excel($table);
    }

    private function params(Request $request): array
    {
        [$start, $end] = $this->periods->current();
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $report = in_array($request->query('report'), Reports::KEYS, true) ? $request->query('report') : 'spending';
        $from = $date($request->query('from')) ?? $start->toDateString();
        $to = $date($request->query('to')) ?? $end->toDateString();

        return [$report, $from, max($from, $to)];
    }
}
