<?php

namespace App\Http\Controllers\Web\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Libraries\ListExportLibrary;
use App\Services\Report\ReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports)
    {
    }

    public function index(): View
    {
        return view('app.reports.index');
    }

    public function bookingsSummary(ReportFilterRequest $request): View
    {
        return view('app.reports.bookings-summary', $this->reports->bookingsSummary($request->from(), $request->to()));
    }

    public function fleetPerformance(ReportFilterRequest $request): View
    {
        return view('app.reports.fleet-performance', $this->reports->fleetPerformance($request->from(), $request->to()));
    }

    public function driverPerformance(ReportFilterRequest $request): View
    {
        return view('app.reports.driver-performance', $this->reports->driverPerformance($request->from(), $request->to()));
    }

    public function vehicleOwnerRevenue(ReportFilterRequest $request): View
    {
        return view('app.reports.vehicle-owner-revenue', $this->reports->vehicleOwnerRevenue($request->from(), $request->to()));
    }

    public function complianceStatus(): View
    {
        return view('app.reports.compliance-status', $this->reports->complianceStatus());
    }

    public function customerActivity(ReportFilterRequest $request): View
    {
        return view('app.reports.customer-activity', $this->reports->customerActivity($request->from(), $request->to()));
    }

    public function exportPdf(string $report, ReportFilterRequest $request): Response
    {
        return ListExportLibrary::pdf($this->reports->exportList($report, $request->from(), $request->to()));
    }

    public function exportExcel(string $report, ReportFilterRequest $request): Response
    {
        return ListExportLibrary::excel($this->reports->exportList($report, $request->from(), $request->to()));
    }
}
