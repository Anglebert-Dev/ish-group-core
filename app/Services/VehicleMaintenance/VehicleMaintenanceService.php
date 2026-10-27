<?php

namespace App\Services\VehicleMaintenance;

use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Libraries\ListExport;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Repositories\VehicleMaintenance\VehicleMaintenanceRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VehicleMaintenanceService extends BaseService
{
    protected const OPEN_STATUSES = [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value];

    public function __construct(protected VehicleMaintenanceRepository $maintenances)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->maintenances->query()->with('vehicle')->orderByDesc('date')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->maintenances->query()
            ->with('vehicle')
            ->orderByDesc('date')
            ->get()
            ->map(fn (VehicleMaintenance $record) => [
                $record->vehicle?->name() ?? '—',
                $record->maintenance_type->label(),
                $record->date?->format('d M Y') ?? '—',
                $record->cost !== null ? number_format((float) $record->cost, 2).' RWF' : '—',
                $record->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.maintenance'), 'maintenance', [
            __('body.vehicle'),
            __('body.maintenance_type'),
            __('body.date'),
            __('body.cost'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $maintenance = new VehicleMaintenance($data);

        $this->maintenances->save($maintenance);
        $this->syncVehicle($maintenance);

        return $this->success($maintenance, __('body.maintenance_created'));
    }

    public function update(VehicleMaintenance $maintenance, array $data): array
    {
        $maintenance->fill($data);

        $this->maintenances->save($maintenance);
        $this->syncVehicle($maintenance);

        return $this->success($maintenance, __('body.maintenance_updated'));
    }

    public function updateStatus(VehicleMaintenance $maintenance, MaintenanceStatus $status): array
    {
        $maintenance->status = $status;

        $this->maintenances->save($maintenance);
        $this->syncVehicle($maintenance);

        return $this->success($maintenance, __('body.maintenance_status_updated'));
    }

    public function delete(VehicleMaintenance $maintenance): array
    {
        $vehicle = $maintenance->vehicle;
        $wasOpen = in_array($maintenance->status->value, self::OPEN_STATUSES, true);

        $maintenance->delete();

        if ($vehicle && $wasOpen) {
            $this->releaseFromMaintenanceIfClear($vehicle);
        }

        return $this->success(null, __('body.maintenance_deleted'));
    }

    /**
     * Keep the vehicle's live mileage and status in step with what this
     * maintenance record says, without letting stale/older edits regress
     * either one.
     */
    protected function syncVehicle(VehicleMaintenance $maintenance): void
    {
        $vehicle = $maintenance->vehicle;

        if (! $vehicle) {
            return;
        }

        $dirty = false;

        // Odometers only go forward — never let a maintenance record's
        // mileage pull the vehicle's live reading backwards.
        if ($maintenance->mileage !== null && $maintenance->mileage > ($vehicle->mileage ?? 0)) {
            $vehicle->mileage = $maintenance->mileage;
            $dirty = true;
        }

        if (in_array($maintenance->status->value, self::OPEN_STATUSES, true)) {
            if ($vehicle->status !== VehicleStatus::Maintenance) {
                $vehicle->status = VehicleStatus::Maintenance;
                $dirty = true;
            }
        } elseif ($maintenance->status === MaintenanceStatus::Completed) {
            $dirty = $this->releaseFromMaintenanceIfClear($vehicle, $maintenance) || $dirty;
        }

        if ($dirty) {
            $vehicle->save();
        }
    }

    /**
     * Only put the vehicle back to Available if no OTHER open maintenance
     * record is still holding it — otherwise a second, still-running job
     * would get silently overridden.
     */
    protected function releaseFromMaintenanceIfClear(Vehicle $vehicle, ?VehicleMaintenance $ignoring = null): bool
    {
        if ($vehicle->status !== VehicleStatus::Maintenance) {
            return false;
        }

        $hasOtherOpenMaintenance = $vehicle->maintenances()
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();

        if ($hasOtherOpenMaintenance) {
            return false;
        }

        $vehicle->status = VehicleStatus::Available;

        return true;
    }
}
