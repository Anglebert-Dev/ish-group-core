<?php

namespace App\Services\VehicleCategory;

use App\Libraries\ListExport;
use App\Libraries\UploadLibrary;
use App\Models\VehicleCategory\VehicleCategory;
use App\Repositories\VehicleCategory\VehicleCategoryRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use App\Enums\VehicleCategoryStatus;

class VehicleCategoryService extends BaseService
{
    public function __construct(protected VehicleCategoryRepository $categories)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->categories->query()
            ->with('vehicles:id,daily_price,vehicle_category_id')
            ->orderBy('name')
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->categories->query()
            ->with('vehicles:id,daily_price,vehicle_category_id')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleCategory $category) => [
                $category->name,
                $category->fleetDailyPriceLabel(),
                $category->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.categories'), 'categories', [
            __('body.category_name'),
            __('body.fleet_daily_rates'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data, ?UploadedFile $image = null): array
    {
        $category = new VehicleCategory($data);

        if ($image) {
            $category->image_path = UploadLibrary::store($image, 'vehicle-categories', 'public');
        }

        $this->categories->save($category);

        return $this->success($category, __('body.vehicle_category_created'));
    }

    public function update(VehicleCategory $category, array $data, ?UploadedFile $image = null): array
    {
        $category->fill($data);

        if ($image) {
            if ($category->image_path) {
                UploadLibrary::delete($category->image_path, 'public');
            }
            $category->image_path = UploadLibrary::store($image, 'vehicle-categories', 'public');
        }

        $this->categories->save($category);

        return $this->success($category, __('body.vehicle_category_updated'));
    }


    public function updateStatus(VehicleCategory $category, VehicleCategoryStatus $status): array
    {
        $category->status = $status;

        $this->categories->save($category);

        return $this->success($category, __('body.vehicle_category_status_updated'));
    }

    public function delete(VehicleCategory $category): array
    {
        if ($category->image_path) {
            UploadLibrary::delete($category->image_path, 'public');
        }

        $category->delete();

        return $this->success(null, __('body.vehicle_category_deleted'));
    }
}
