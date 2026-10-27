<?php

namespace App\Services\VehicleOwner;

use App\Enums\VehicleOwnerStatus;
use App\Libraries\ListExport;
use App\Models\VehicleOwner\VehicleOwner;
use App\Repositories\VehicleOwner\VehicleOwnerRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VehicleOwnerService extends BaseService
{
    public function __construct(protected VehicleOwnerRepository $owners)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->owners->paginateList();
    }

    public function exportList(): ListExport
    {
        $rows = $this->owners->query()
            ->withCount('vehicles')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (VehicleOwner $owner) => [
                $owner->fullName(),
                $owner->phone,
                $owner->splitLabel(),
                (string) $owner->vehicles_count,
                $owner->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.vehicle_owners'), 'vehicle-owners', [
            __('body.full_name'),
            __('body.phone'),
            __('body.income_split'),
            __('body.vehicles'),
            __('body.status'),
        ], $rows);
    }

    public function catalog(): Collection
    {
        return $this->owners->catalog();
    }

    public function activeCatalog(): Collection
    {
        return $this->owners->activeCatalog();
    }

    public function create(array $data): array
    {
        $owner = new VehicleOwner($data);

        $this->owners->save($owner);

        return $this->success($owner, __('body.vehicle_owner_created'));
    }

    public function update(VehicleOwner $owner, array $data): array
    {
        $owner->fill($data);

        $this->owners->save($owner);

        return $this->success($owner, __('body.vehicle_owner_updated'));
    }

    public function updateStatus(VehicleOwner $owner, VehicleOwnerStatus $status): array
    {
        $owner->status = $status;

        $this->owners->save($owner);

        return $this->success($owner, __('body.vehicle_owner_status_updated'));
    }

    public function delete(VehicleOwner $owner): array
    {
        $owner->delete();

        return $this->success(null, __('body.vehicle_owner_deleted'));
    }
}
