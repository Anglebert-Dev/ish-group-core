<?php

namespace App\Services\Station;

use App\Libraries\ListExport;
use App\Models\Station\Station;
use App\Repositories\Station\StationRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Enums\StationStatus;

class StationService extends BaseService
{
    public function __construct(protected StationRepository $stations)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->stations->query()->orderBy('name')->paginate(15);
    }

    public function activeOptions(): \Illuminate\Support\Collection
    {
        return $this->stations->query()
            ->where('status', \App\Enums\StationStatus::Active->value)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function exportList(): ListExport
    {
        $rows = $this->stations->query()
            ->orderBy('name')
            ->get()
            ->map(fn (Station $station) => [
                $station->name,
                $station->code,
                $station->city ?? '—',
                ucfirst($station->status->value),
            ])
            ->all();

        return ListExport::make(__('body.stations'), 'stations', [
            __('body.station_name'),
            __('body.code'),
            __('body.city'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $station = new Station($data);

        $this->stations->save($station);

        return $this->success($station, __('body.station_created'));
    }

    public function update(Station $station, array $data): array
    {
        $station->fill($data);

        $this->stations->save($station);

        return $this->success($station, __('body.station_updated'));
    }


    public function updateStatus(Station $station, StationStatus $status): array
    {
        $station->status = $status;

        $this->stations->save($station);

        return $this->success($station, __('body.station_status_updated'));
    }

    public function delete(Station $station): array
    {
        $station->delete();

        return $this->success(null, __('body.station_deleted'));
    }
}
