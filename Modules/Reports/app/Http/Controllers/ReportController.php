<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Reports\Services\ReportService;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        [$report, $from, $to] = $this->params($request);

        return view('reports::index', [
            'available' => $this->reports->available(),
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'result' => $report ? $this->reports->run($report, $from, $to) : null,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        [$report, $from, $to] = $this->params($request);
        abort_unless($report && in_array($format, ['pdf', 'excel'], true), 404);
        $table = $this->reports->run($report, $from, $to)['table'];
        $table->title .= ' · '.$from.' – '.$to;

        audit(\App\Enums\ActivityLogAction::Exported, __('reports::reports.audit_export', ['report' => __('reports::reports.'.$report)]));

        return $format === 'pdf' ? ListExportLibrary::pdf($table) : ListExportLibrary::excel($table);
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function params(Request $request): array
    {
        $available = $this->reports->available();
        $report = in_array($request->query('report'), $available, true) ? $request->query('report') : ($available[0] ?? null);
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to = $this->date($request->query('to')) ?? now()->toDateString();

        return [$report, $from, max($from, $to)];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
