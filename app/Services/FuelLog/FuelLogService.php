<?php

namespace App\Services\FuelLog;

use App\Libraries\ListExport;
use App\Models\FuelLog\FuelLog;
use App\Models\Vehicle\Vehicle;
use App\Repositories\FuelLog\FuelLogRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Enums\FuelLogStatus;

class FuelLogService extends BaseService
{
    public function __construct(protected FuelLogRepository $fuelLogs)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->fuelLogs->query()
            ->with(['vehicle', 'driver'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->fuelLogs->query()
            ->with(['vehicle', 'driver'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FuelLog $record) => [
                $record->vehicle?->name() ?? '—',
                $record->date?->format('d M Y') ?? '—',
                number_format((float) $record->liters, 2).' L',
                number_format((float) $record->cost, 2).' RWF',
                $record->gas_station_name ?: '—',
                $record->driver?->fullName() ?? '—',
                $record->fuel_type->label(),
                $record->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.fuel_logs'), 'fuel-logs', [
            __('body.vehicle'),
            __('body.date'),
            __('body.liters'),
            __('body.cost'),
            __('body.gas_station_name'),
            __('body.driver'),
            __('body.fuel_type'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $data = $this->applyVehicleDefaults($data);

        $fuelLog = new FuelLog($data);

        $this->fuelLogs->save($fuelLog);
        $this->syncVehicleMileage($fuelLog);

        return $this->success($fuelLog, __('body.fuel_log_created'));
    }

    public function update(FuelLog $fuelLog, array $data): array
    {
        $data = $this->applyVehicleDefaults($data);

        $fuelLog->fill($data);

        $this->fuelLogs->save($fuelLog);
        $this->syncVehicleMileage($fuelLog);

        return $this->success($fuelLog, __('body.fuel_log_updated'));
    }


    public function updateStatus(FuelLog $fuelLog, FuelLogStatus $status): array
    {
        $fuelLog->status = $status;

        $this->fuelLogs->save($fuelLog);

        return $this->success($fuelLog, __('body.fuel_log_status_updated'));
    }

    public function delete(FuelLog $fuelLog): array
    {
        $fuelLog->delete();

        return $this->success(null, __('body.fuel_log_deleted'));
    }

    protected function applyVehicleDefaults(array $data): array
    {
        if (! empty($data['fuel_type']) || empty($data['vehicle_id'])) {
            return $data;
        }

        $vehicle = Vehicle::query()->find($data['vehicle_id']);

        if ($vehicle?->fuel_type) {
            $data['fuel_type'] = $vehicle->fuel_type->value;
        }

        return $data;
    }

    protected function syncVehicleMileage(FuelLog $fuelLog): void
    {
        $vehicle = $fuelLog->vehicle;

        if (! $vehicle || $fuelLog->mileage === null) {
            return;
        }

        if ($fuelLog->mileage > ($vehicle->mileage ?? 0)) {
            $vehicle->mileage = $fuelLog->mileage;
            $vehicle->save();
        }
    }
}
