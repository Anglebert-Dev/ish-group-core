<?php

namespace App\Services\Driver;

use App\Libraries\ListExport;
use App\Libraries\UploadLibrary;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverDocument;
use App\Repositories\Driver\DriverRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use App\Enums\DriverStatus;

class DriverService extends BaseService
{
    public function __construct(protected DriverRepository $drivers)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->drivers->query()->orderBy('first_name')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->drivers->query()
            ->orderBy('first_name')
            ->get()
            ->map(fn (Driver $driver) => [
                $driver->fullName(),
                $driver->phone,
                $driver->license_number,
                $driver->availability_status->label(),
                ucfirst($driver->status->value),
            ])
            ->all();

        return ListExport::make(__('body.drivers'), 'drivers', [
            __('body.full_name'),
            __('body.phone'),
            __('body.license_number'),
            __('body.availability_status'),
            __('body.status'),
        ], $rows);
    }

    /**
     * @param  array<string, UploadedFile>  $documents  keyed by document type
     */
    public function create(array $data, array $documents = []): array
    {
        $driver = new Driver($data);

        $this->drivers->save($driver);

        $this->uploadDocuments($driver, $documents);

        return $this->success($driver, __('body.driver_created'));
    }

    /**
     * @param  array<string, UploadedFile>  $documents  keyed by document type
     */
    public function update(Driver $driver, array $data, array $documents = []): array
    {
        $driver->fill($data);

        $this->drivers->save($driver);

        $this->uploadDocuments($driver, $documents);

        return $this->success($driver, __('body.driver_updated'));
    }

    /**
     * @param  array<string, UploadedFile>  $documents  keyed by document type
     */
    protected function uploadDocuments(Driver $driver, array $documents): void
    {
        foreach ($documents as $type => $file) {
            if ($file instanceof UploadedFile) {
                $this->uploadDocument($driver, $file, $type);
            }
        }
    }


    public function updateStatus(Driver $driver, DriverStatus $status): array
    {
        $driver->status = $status;

        $this->drivers->save($driver);

        return $this->success($driver, __('body.driver_status_updated'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLicense(Driver $driver, array $data, ?UploadedFile $licenseDocument = null): array
    {
        $driver->fill([
            'national_id' => $data['national_id'],
            'license_number' => $data['license_number'],
            'license_expiry' => $data['license_expiry'],
            'license_category' => $data['license_category'] ?? null,
        ]);

        $this->drivers->save($driver);

        if ($licenseDocument instanceof UploadedFile) {
            $this->uploadDocument($driver, $licenseDocument, 'license');
        }

        return $this->success($driver, __('body.driver_license_updated'));
    }

    public function delete(Driver $driver): array
    {
        $driver->delete();

        return $this->success(null, __('body.driver_deleted'));
    }

    public function uploadDocument(Driver $driver, UploadedFile $file, string $type): array
    {
        $path = UploadLibrary::store($file, 'drivers/'.$driver->uuid);

        $document = DriverDocument::create([
            'driver_id' => $driver->id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
        ]);

        return $this->success($document, __('body.document_uploaded'));
    }

    public function deleteDocument(DriverDocument $document): array
    {
        UploadLibrary::delete($document->file_path);
        $document->delete();

        return $this->success(null, __('body.document_deleted'));
    }
}
