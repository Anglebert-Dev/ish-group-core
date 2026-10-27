<?php

namespace App\Services\Report;

use App\Enums\BookingStatus;
use App\Enums\DocumentAlertLevel;
use App\Enums\FuelLogStatus;
use App\Enums\RentalType;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Libraries\ListExport;
use App\Models\Booking\Booking;
use App\Models\Driver\Driver;
use App\Models\FuelLog\FuelLog;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Models\VehicleOwner\VehicleOwner;

/**
 * Operational reports across the app. Deliberately does NOT reimplement
 * the Financial Statements (Trial Balance / P&L / Balance Sheet) — those
 * already exist as real GL-backed reports under FinancialStatementService;
 * this service covers everything else (bookings, fleet, drivers, vehicle
 * owners, compliance, customers).
 */
class ReportService
{
    public function bookingsSummary(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        [$start, $end] = $this->boundsFor($from, $to);

        $base = fn () => Booking::query()->whereBetween('pickup_at', [$start, $end]);

        $byStatus = $base()
            ->selectRaw('status, count(*) as aggregate, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('status')
            ->get();

        $byRentalType = $base()
            ->selectRaw('rental_type, count(*) as aggregate, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('rental_type')
            ->get();

        $byPaymentOption = $base()
            ->selectRaw('payment_option, count(*) as aggregate, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('payment_option')
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'by_status' => $byStatus,
            'by_rental_type' => $byRentalType,
            'by_payment_option' => $byPaymentOption,
            'total_bookings' => $base()->count(),
            'total_revenue' => (float) $base()->whereNotIn('status', $this->excludedRevenueStatuses())->sum('total_amount'),
        ];
    }

    public function fleetPerformance(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        [$start, $end] = $this->boundsFor($from, $to);

        $revenueByVehicle = Booking::query()
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotIn('status', $this->excludedRevenueStatuses())
            ->whereNotNull('assigned_vehicle_id')
            ->selectRaw('assigned_vehicle_id, count(*) as trips, COALESCE(SUM(total_amount), 0) as revenue')
            ->groupBy('assigned_vehicle_id')
            ->get()
            ->keyBy('assigned_vehicle_id');

        $maintenanceByVehicle = VehicleMaintenance::query()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('vehicle_id, COALESCE(SUM(cost), 0) as maintenance_cost')
            ->groupBy('vehicle_id')
            ->pluck('maintenance_cost', 'vehicle_id');

        $fuelByVehicle = FuelLog::query()
            ->whereBetween('date', [$from, $to])
            ->where('status', FuelLogStatus::Recorded->value)
            ->selectRaw('vehicle_id, COALESCE(SUM(cost), 0) as fuel_cost')
            ->groupBy('vehicle_id')
            ->pluck('fuel_cost', 'vehicle_id');

        $rows = Vehicle::query()
            ->with('category')
            ->get()
            ->map(function (Vehicle $vehicle) use ($revenueByVehicle, $maintenanceByVehicle, $fuelByVehicle) {
                $revenueRow = $revenueByVehicle->get($vehicle->id);
                $revenue = (float) ($revenueRow->revenue ?? 0);
                $maintenanceCost = (float) ($maintenanceByVehicle[$vehicle->id] ?? 0);
                $fuelCost = (float) ($fuelByVehicle[$vehicle->id] ?? 0);

                return [
                    'vehicle' => $vehicle,
                    'trips' => (int) ($revenueRow->trips ?? 0),
                    'revenue' => $revenue,
                    'maintenance_cost' => $maintenanceCost,
                    'fuel_cost' => $fuelCost,
                    'net' => $revenue - $maintenanceCost - $fuelCost,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'trips' => $rows->sum('trips'),
                'revenue' => $rows->sum('revenue'),
                'maintenance_cost' => $rows->sum('maintenance_cost'),
                'fuel_cost' => $rows->sum('fuel_cost'),
                'net' => $rows->sum('net'),
            ],
        ];
    }

    public function driverPerformance(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        [$start, $end] = $this->boundsFor($from, $to);

        $jobsByDriver = Booking::query()
            ->where('rental_type', RentalType::Chauffeur->value)
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotIn('status', $this->excludedRevenueStatuses())
            ->whereNotNull('assigned_driver_id')
            ->selectRaw('assigned_driver_id, count(*) as jobs, COALESCE(SUM(chauffeur_fee), 0) as chauffeur_revenue')
            ->groupBy('assigned_driver_id')
            ->get()
            ->keyBy('assigned_driver_id');

        $rows = Driver::query()
            ->get()
            ->map(function (Driver $driver) use ($jobsByDriver) {
                $jobRow = $jobsByDriver->get($driver->id);

                return [
                    'driver' => $driver,
                    'jobs' => (int) ($jobRow->jobs ?? 0),
                    'chauffeur_revenue' => (float) ($jobRow->chauffeur_revenue ?? 0),
                    'license_alert_level' => $driver->licenseAlertLevel(),
                    'license_alert_summary' => $driver->licenseAlertSummary(),
                ];
            })
            ->sortByDesc('jobs')
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ];
    }

    public function vehicleOwnerRevenue(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        [$start, $end] = $this->boundsFor($from, $to);

        $rows = VehicleOwner::query()
            ->with('vehicles')
            ->get()
            ->map(function (VehicleOwner $owner) use ($start, $end) {
                $vehicleIds = $owner->vehicles->pluck('id')->all();

                $revenue = $vehicleIds === []
                    ? 0.0
                    : (float) Booking::query()
                        ->whereIn('assigned_vehicle_id', $vehicleIds)
                        ->whereBetween('pickup_at', [$start, $end])
                        ->whereNotIn('status', $this->excludedRevenueStatuses())
                        ->sum('total_amount');

                $grossRevenue = number_format($revenue, 2, '.', '');
                $ownerPayout = bcdiv(bcmul($grossRevenue, (string) $owner->owner_share_percent, 4), '100', 2);
                $companyRetained = bcsub($grossRevenue, $ownerPayout, 2);

                return [
                    'owner' => $owner,
                    'vehicle_count' => count($vehicleIds),
                    'gross_revenue' => $grossRevenue,
                    'owner_payout' => $ownerPayout,
                    'company_retained' => $companyRetained,
                ];
            })
            ->values();

        $companyOwnedRevenue = number_format(
            (float) Booking::query()
                ->whereNotNull('assigned_vehicle_id')
                ->whereHas('assignedVehicle', fn ($q) => $q->whereNull('vehicle_owner_id'))
                ->whereBetween('pickup_at', [$start, $end])
                ->whereNotIn('status', $this->excludedRevenueStatuses())
                ->sum('total_amount'),
            2, '.', ''
        );

        $totalOwnersRevenue = $rows->reduce(fn ($carry, $row) => bcadd($carry, $row['gross_revenue'], 2), '0.00');

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'company_owned_revenue' => $companyOwnedRevenue,
            'grand_total' => bcadd($totalOwnersRevenue, $companyOwnedRevenue, 2),
        ];
    }

    public function complianceStatus(): array
    {
        $severityOf = fn (DocumentAlertLevel $level) => match ($level) {
            DocumentAlertLevel::Red => 0,
            DocumentAlertLevel::Yellow => 1,
            DocumentAlertLevel::Unknown => 2,
            DocumentAlertLevel::Green => 3,
        };

        $drivers = Driver::query()
            ->get()
            ->map(fn (Driver $driver) => [
                'driver' => $driver,
                'level' => $driver->licenseAlertLevel(),
                'summary' => $driver->licenseAlertSummary(),
            ])
            ->sortBy(fn ($row) => $severityOf($row['level']))
            ->values();

        $vehicles = Vehicle::query()
            ->with('owner')
            ->get()
            ->map(fn (Vehicle $vehicle) => [
                'vehicle' => $vehicle,
                'insurance_level' => $vehicle->insuranceAlertLevel(),
                'insurance_summary' => $vehicle->insuranceAlertSummary(),
                'inspection_level' => $vehicle->inspectionAlertLevel(),
                'inspection_summary' => $vehicle->inspectionAlertSummary(),
            ])
            ->sortBy(fn ($row) => min($severityOf($row['insurance_level']), $severityOf($row['inspection_level'])))
            ->values();

        return [
            'drivers' => $drivers,
            'vehicles' => $vehicles,
        ];
    }

    public function customerActivity(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        [$start, $end] = $this->boundsFor($from, $to);

        $individualRows = Booking::query()
            ->whereNotNull('customer_id')
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotIn('status', $this->excludedRevenueStatuses())
            ->selectRaw('customer_id, count(*) as bookings, COALESCE(SUM(total_amount), 0) as spend')
            ->groupBy('customer_id')
            ->orderByDesc('spend')
            ->limit(50)
            ->with('customer')
            ->get();

        $corporateRows = Booking::query()
            ->whereNotNull('corporate_customer_id')
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotIn('status', $this->excludedRevenueStatuses())
            ->selectRaw('corporate_customer_id, count(*) as bookings, COALESCE(SUM(total_amount), 0) as spend')
            ->groupBy('corporate_customer_id')
            ->orderByDesc('spend')
            ->limit(50)
            ->with('corporateCustomer')
            ->get();

        $baseSpend = fn (string $column) => (float) Booking::query()
            ->whereNotNull($column)
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotIn('status', $this->excludedRevenueStatuses())
            ->sum('total_amount');

        return [
            'from' => $from,
            'to' => $to,
            'individual_rows' => $individualRows,
            'corporate_rows' => $corporateRows,
            'individual_total' => $baseSpend('customer_id'),
            'corporate_total' => $baseSpend('corporate_customer_id'),
        ];
    }

    public function exportList(string $report, ?string $from, ?string $to): ListExport
    {
        abort_unless(in_array($report, ReportFilterRequest::REPORTS, true), 404);

        return match ($report) {
            'bookings-summary' => $this->exportBookingsSummary($from, $to),
            'fleet-performance' => $this->exportFleetPerformance($from, $to),
            'driver-performance' => $this->exportDriverPerformance($from, $to),
            'vehicle-owner-revenue' => $this->exportVehicleOwnerRevenue($from, $to),
            'compliance-status' => $this->exportComplianceStatus(),
            'customer-activity' => $this->exportCustomerActivity($from, $to),
        };
    }

    private function exportBookingsSummary(?string $from, ?string $to): ListExport
    {
        $data = $this->bookingsSummary($from, $to);
        $rows = [];

        foreach ($data['by_status'] as $row) {
            $rows[] = ['Status', $row->status->label(), (string) $row->aggregate, $this->money($row->revenue)];
        }
        foreach ($data['by_rental_type'] as $row) {
            $rows[] = ['Rental Type', $row->rental_type->label(), (string) $row->aggregate, $this->money($row->revenue)];
        }
        foreach ($data['by_payment_option'] as $row) {
            $rows[] = ['Payment Option', $row->payment_option->label(), (string) $row->aggregate, $this->money($row->revenue)];
        }

        return ListExport::make(__('body.bookings_summary'), 'bookings-summary',
            ['Breakdown', 'Value', 'Bookings', 'Revenue'], $rows);
    }

    private function exportFleetPerformance(?string $from, ?string $to): ListExport
    {
        $rows = $this->fleetPerformance($from, $to)['rows']->map(fn ($row) => [
            $row['vehicle']->name(),
            $row['vehicle']->plate_number,
            $row['vehicle']->category?->name ?? '',
            (string) $row['trips'],
            $this->money($row['revenue']),
            $this->money($row['maintenance_cost']),
            $this->money($row['fuel_cost']),
            $this->money($row['net']),
        ])->all();

        return ListExport::make(__('body.fleet_performance'), 'fleet-performance',
            ['Vehicle', 'Plate', 'Category', 'Trips', 'Revenue', 'Maintenance Cost', 'Fuel Cost', 'Net Contribution'], $rows);
    }

    private function exportDriverPerformance(?string $from, ?string $to): ListExport
    {
        $rows = $this->driverPerformance($from, $to)['rows']->map(fn ($row) => [
            $row['driver']->fullName(),
            (string) $row['jobs'],
            $this->money($row['chauffeur_revenue']),
            $row['license_alert_summary'],
        ])->all();

        return ListExport::make(__('body.driver_performance'), 'driver-performance',
            ['Driver', 'Chauffeur Jobs', 'Chauffeur-Fee Revenue', 'License Status'], $rows);
    }

    private function exportVehicleOwnerRevenue(?string $from, ?string $to): ListExport
    {
        $data = $this->vehicleOwnerRevenue($from, $to);

        $rows = $data['rows']->map(fn ($row) => [
            $row['owner']->fullName(),
            (string) $row['vehicle_count'],
            $row['gross_revenue'],
            $row['owner_payout'],
            $row['company_retained'],
        ])->all();

        $rows[] = ['Company-Owned Vehicles', '', $data['company_owned_revenue'], '', ''];
        $rows[] = ['Grand Total', '', $data['grand_total'], '', ''];

        return ListExport::make(__('body.vehicle_owner_revenue'), 'vehicle-owner-revenue',
            ['Owner', 'Vehicles', 'Gross Revenue', 'Owner Payout', 'Company Retained'], $rows);
    }

    private function exportComplianceStatus(): ListExport
    {
        $data = $this->complianceStatus();
        $rows = [];

        foreach ($data['drivers'] as $row) {
            $rows[] = ['Driver', $row['driver']->fullName(), $row['summary'], ''];
        }
        foreach ($data['vehicles'] as $row) {
            $rows[] = ['Vehicle', $row['vehicle']->name(), $row['insurance_summary'], $row['inspection_summary']];
        }

        return ListExport::make(__('body.compliance_status'), 'compliance-status',
            ['Type', 'Subject', 'Insurance / License', 'Inspection'], $rows);
    }

    private function exportCustomerActivity(?string $from, ?string $to): ListExport
    {
        $data = $this->customerActivity($from, $to);
        $rows = [];

        foreach ($data['individual_rows'] as $row) {
            $rows[] = ['Individual', $row->customer?->name ?? '—', (string) $row->bookings, $this->money($row->spend)];
        }
        foreach ($data['corporate_rows'] as $row) {
            $rows[] = ['Corporate', $row->corporateCustomer?->company_name ?? '—', (string) $row->bookings, $this->money($row->spend)];
        }

        return ListExport::make(__('body.customer_activity'), 'customer-activity',
            ['Type', 'Customer', 'Bookings', 'Total Spend'], $rows);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        return [
            $from ?: now()->startOfMonth()->toDateString(),
            $to ?: now()->toDateString(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function boundsFor(string $from, string $to): array
    {
        return [$from.' 00:00:00', $to.' 23:59:59'];
    }

    /**
     * @return list<string>
     */
    private function excludedRevenueStatuses(): array
    {
        return [BookingStatus::Cancelled->value, BookingStatus::NoShow->value];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
