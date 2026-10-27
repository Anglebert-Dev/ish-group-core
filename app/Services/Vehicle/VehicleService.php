<?php

namespace App\Services\Vehicle;

use App\Enums\VehicleStatus;
use App\Libraries\ListExport;
use App\Libraries\UploadLibrary;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleImage;
use App\Repositories\Vehicle\VehicleRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class VehicleService extends BaseService
{
    private const DOCUMENT_FIELDS = [
        'insurance_document',
        'inspection_document',
        'carte_jaune_document',
    ];

    public function __construct(protected VehicleRepository $vehicles)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->vehicles->query()->with(['category', 'station', 'owner'])->latest('id')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->vehicles->query()
            ->with(['category', 'owner'])
            ->latest('id')
            ->get()
            ->map(fn (Vehicle $vehicle) => [
                $vehicle->name(),
                $vehicle->plate_number,
                $vehicle->category?->name ?? '—',
                number_format((float) ($vehicle->dailyPrice() ?? 0), 0).' RWF/day',
                $vehicle->owner
                    ? $vehicle->owner->fullName().' ('.__('body.outsider_owner').')'
                    : __('body.company_fleet'),
                $vehicle->transmission->label(),
                $vehicle->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.vehicles'), 'vehicles', [
            __('body.vehicle'),
            __('body.plate_number'),
            __('body.category'),
            __('body.daily_rate'),
            __('body.vehicle_owner'),
            __('body.transmission'),
            __('body.status'),
        ], $rows);
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $images
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function create(array $data, array $images = [], array $documents = []): array
    {
        $data = $this->withoutDocumentUploads($data);
        $vehicle = new Vehicle($data);

        $this->vehicles->save($vehicle);
        $this->storeComplianceDocuments($vehicle, $documents);

        if (! empty($images)) {
            $this->uploadImages($vehicle, $images);
        }

        return $this->success($vehicle, __('body.vehicle_created'));
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $images
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function update(Vehicle $vehicle, array $data, array $images = [], array $documents = []): array
    {
        $data = $this->withoutDocumentUploads($data);
        $vehicle->fill($data);

        $this->vehicles->save($vehicle);
        $this->storeComplianceDocuments($vehicle, $documents);

        if (! empty($images)) {
            $this->uploadImages($vehicle, $images);
        }

        return $this->success($vehicle, __('body.vehicle_updated'));
    }

    public function updateStatus(Vehicle $vehicle, VehicleStatus $status): array
    {
        $vehicle->status = $status;

        $this->vehicles->save($vehicle);

        return $this->success($vehicle, __('body.vehicle_status_updated'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function updateCompliance(Vehicle $vehicle, array $data, array $documents = []): array
    {
        $data = $this->withoutDocumentUploads($data);
        $vehicle->fill($data);

        $this->vehicles->save($vehicle);
        $this->storeComplianceDocuments($vehicle, $documents);

        return $this->success($vehicle, __('body.vehicle_compliance_updated'));
    }

    public function delete(Vehicle $vehicle): array
    {
        $vehicle->delete();

        return $this->success(null, __('body.vehicle_deleted'));
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $files
     */
    public function uploadImages(Vehicle $vehicle, array $files): array
    {
        $nextOrder = ((int) $vehicle->images()->max('sort_order')) + 1;

        foreach ($files as $file) {
            $path = UploadLibrary::store($file, 'vehicles/'.$vehicle->uuid, 'public');

            VehicleImage::create([
                'vehicle_id' => $vehicle->id,
                'file_path' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }

        return $this->success(null, __('body.images_uploaded'));
    }

    public function deleteImage(VehicleImage $image): array
    {
        UploadLibrary::delete($image->file_path, 'public');
        $image->delete();

        return $this->success(null, __('body.image_deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withoutDocumentUploads(array $data): array
    {
        foreach (self::DOCUMENT_FIELDS as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * @param  array<string, UploadedFile|null>  $documents
     */
    protected function storeComplianceDocuments(Vehicle $vehicle, array $documents): void
    {
        $dirty = false;

        foreach (self::DOCUMENT_FIELDS as $field) {
            $file = $documents[$field] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if ($vehicle->{$field}) {
                UploadLibrary::delete($vehicle->{$field}, 'public');
            }

            $vehicle->{$field} = UploadLibrary::store(
                $file,
                'vehicles/'.$vehicle->uuid.'/compliance',
                'public'
            );
            $dirty = true;
        }

        if ($dirty) {
            $this->vehicles->save($vehicle);
        }
    }
}
