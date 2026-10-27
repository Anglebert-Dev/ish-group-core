<?php

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\DocumentAlertLevel;
use App\Enums\InvoiceStatus;
use App\Enums\VehicleStatus;
use App\Helpers\AuthHelper;
use App\Libraries\DocumentExpiryLibrary;
use App\Models\Booking\Booking;
use App\Models\Driver\Driver;
use App\Models\Invoice\Invoice;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class DashboardService
{
    private const ACTIVE_BOOKING_STATUSES = [
        BookingStatus::Pending->value,
        BookingStatus::Confirmed->value,
        BookingStatus::ReadyForPickup->value,
        BookingStatus::ActiveRental->value,
    ];

    private const UPCOMING_PICKUP_STATUSES = [
        BookingStatus::Confirmed->value,
        BookingStatus::ReadyForPickup->value,
    ];

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $canBookings = AuthHelper::can('list bookings.booking');
        $canFinance = AuthHelper::can('list finance.invoice');
        $canVehicles = AuthHelper::can('list fleet.vehicle');
        $canDrivers = AuthHelper::can('list drivers.driver');

        return [
            'stats' => $this->stats($canBookings, $canFinance, $canVehicles),
            'recentBookings' => $canBookings ? $this->recentBookings() : new Collection,
            'fleetStatus' => $canVehicles ? $this->fleetStatusBreakdown() : [],
            'upcomingPickups' => $canBookings ? $this->upcomingPickups() : new Collection,
            'overdueReturns' => $canBookings ? $this->overdueReturns() : new Collection,
            'pendingBookings' => $canBookings ? $this->pendingBookings() : new Collection,
            'bookingVolume' => $canBookings ? $this->bookingsLastDays(7) : [],
            'complianceAlerts' => ($canDrivers || $canVehicles)
                ? $this->complianceAlerts($canDrivers, $canVehicles)
                : collect(),
        ];
    }

    public function stats(bool $canBookings = true, bool $canFinance = true, bool $canVehicles = true): array
    {
        $bookingCounts = $canBookings
            ? Booking::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
            : collect();

        $vehicleCounts = $canVehicles
            ? Vehicle::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
            : collect();

        $activeBookings = 0;
        foreach (self::ACTIVE_BOOKING_STATUSES as $status) {
            $activeBookings += (int) ($bookingCounts[$status] ?? 0);
        }

        return [
            'active_bookings' => $canBookings ? $activeBookings : null,
            'pending_bookings' => $canBookings ? (int) ($bookingCounts[BookingStatus::Pending->value] ?? 0) : null,
            'ready_for_pickup' => $canBookings ? (int) ($bookingCounts[BookingStatus::ReadyForPickup->value] ?? 0) : null,
            'vehicles_on_hire' => $canVehicles ? (int) ($vehicleCounts[VehicleStatus::OnHire->value] ?? 0) : null,
            'vehicles_available' => $canVehicles ? (int) ($vehicleCounts[VehicleStatus::Available->value] ?? 0) : null,
            'vehicles_in_maintenance' => $canVehicles ? (int) ($vehicleCounts[VehicleStatus::Maintenance->value] ?? 0) : null,
            'overdue_returns' => $canBookings ? $this->overdueReturnsQuery()->count() : null,
            'unpaid_invoices' => $canFinance
                ? Invoice::query()->where('status', InvoiceStatus::Issued->value)->count()
                : null,
            'revenue_this_month' => $canFinance
                ? (float) Booking::query()
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value])
                    ->sum('total_amount')
                : null,
        ];
    }

    public function recentBookings(int $limit = 5): Collection
    {
        return Booking::with(['customer', 'corporateCustomer', 'category', 'assignedVehicle'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * @return array<int, array{label: string, count: int, color: string}>
     */
    public function fleetStatusBreakdown(): array
    {
        $counts = Vehicle::selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $colors = [
            VehicleStatus::Available->value => 'success',
            VehicleStatus::OnHire->value => 'brand',
            VehicleStatus::Reserved->value => 'brand',
            VehicleStatus::Maintenance->value => 'warning',
            VehicleStatus::Inactive->value => 'muted',
        ];

        return collect(VehicleStatus::cases())
            ->map(fn (VehicleStatus $status) => [
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
                'color' => $colors[$status->value] ?? 'muted',
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    public function upcomingPickups(int $limit = 3): Collection
    {
        return Booking::with(['customer', 'corporateCustomer', 'assignedVehicle', 'category'])
            ->whereIn('status', self::UPCOMING_PICKUP_STATUSES)
            ->where('pickup_at', '>=', now()->startOfDay())
            ->orderBy('pickup_at')
            ->take($limit)
            ->get();
    }

    public function overdueReturns(int $limit = 5): Collection
    {
        return $this->overdueReturnsQuery()
            ->with(['customer', 'corporateCustomer', 'assignedVehicle', 'category'])
            ->orderBy('return_at')
            ->take($limit)
            ->get();
    }

    public function pendingBookings(int $limit = 5): Collection
    {
        return Booking::with(['customer', 'corporateCustomer', 'assignedVehicle', 'category'])
            ->where('status', BookingStatus::Pending->value)
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * @return list<array{label: string, date: string, count: int}>
     */
    public function bookingsLastDays(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = Booking::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $out[] = [
                'label' => $day->format('D'),
                'date' => $day->toDateString(),
                'count' => (int) ($rows[$day->toDateString()] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Expiring / expired driver licenses and vehicle insurance / inspection (Atlas-style ≤30 days).
     *
     * @return SupportCollection<int, array{subject: string, document: string, summary: string, level: DocumentAlertLevel, href: string, expires_at: \Carbon\CarbonInterface|null}>
     */
    public function complianceAlerts(bool $canDrivers = true, bool $canVehicles = true, int $limit = 8): SupportCollection
    {
        $threshold = now()->addDays(DocumentExpiryLibrary::YELLOW_DAYS)->toDateString();
        $alerts = collect();

        if ($canDrivers) {
            Driver::query()
                ->whereNotNull('license_expiry')
                ->whereDate('license_expiry', '<=', $threshold)
                ->orderBy('license_expiry')
                ->get()
                ->each(function (Driver $driver) use ($alerts) {
                    $alerts->push([
                        'subject' => $driver->fullName(),
                        'document' => __('body.driving_license'),
                        'summary' => $driver->licenseAlertSummary(),
                        'level' => $driver->licenseAlertLevel(),
                        'href' => route('drivers.show', $driver),
                        'expires_at' => $driver->license_expiry,
                    ]);
                });
        }

        if ($canVehicles) {
            Vehicle::query()
                ->where(function ($query) use ($threshold) {
                    $query->where(function ($inner) use ($threshold) {
                        $inner->whereNotNull('insurance_expires_at')
                            ->whereDate('insurance_expires_at', '<=', $threshold);
                    })->orWhere(function ($inner) use ($threshold) {
                        $inner->whereNotNull('inspection_expires_at')
                            ->whereDate('inspection_expires_at', '<=', $threshold);
                    });
                })
                ->get()
                ->each(function (Vehicle $vehicle) use ($alerts) {
                    if ($vehicle->insuranceAlertLevel()->isAttention()) {
                        $alerts->push([
                            'subject' => $vehicle->name().' ('.$vehicle->plate_number.')',
                            'document' => __('body.insurance'),
                            'summary' => $vehicle->insuranceAlertSummary(),
                            'level' => $vehicle->insuranceAlertLevel(),
                            'href' => route('fleet.vehicles.show', $vehicle),
                            'expires_at' => $vehicle->insurance_expires_at,
                        ]);
                    }

                    if ($vehicle->inspectionAlertLevel()->isAttention()) {
                        $alerts->push([
                            'subject' => $vehicle->name().' ('.$vehicle->plate_number.')',
                            'document' => __('body.inspection'),
                            'summary' => $vehicle->inspectionAlertSummary(),
                            'level' => $vehicle->inspectionAlertLevel(),
                            'href' => route('fleet.vehicles.show', $vehicle),
                            'expires_at' => $vehicle->inspection_expires_at,
                        ]);
                    }
                });
        }

        return $alerts
            ->sortBy([
                fn (array $row) => $row['level'] === DocumentAlertLevel::Red ? 0 : 1,
                fn (array $row) => optional($row['expires_at'])->timestamp ?? PHP_INT_MAX,
            ])
            ->values()
            ->take($limit);
    }

    private function overdueReturnsQuery()
    {
        return Booking::query()
            ->where('status', BookingStatus::ActiveRental->value)
            ->where('return_at', '<', now());
    }
}
