<?php

namespace App\Services\Supplier;

use App\Libraries\ListExport;
use App\Models\Supplier\Supplier;
use App\Repositories\Supplier\SupplierRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Enums\SupplierStatus;

class SupplierService extends BaseService
{
    public function __construct(protected SupplierRepository $suppliers)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->suppliers->paginateList();
    }

    public function exportList(): ListExport
    {
        $rows = $this->suppliers->query()
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                $supplier->name,
                $supplier->type->label(),
                $supplier->contact_name ?? '—',
                $supplier->phone ?? '—',
                $supplier->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.suppliers'), 'suppliers', [
            __('body.supplier_name'),
            __('body.supplier_type'),
            __('body.contact_name'),
            __('body.phone'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $supplier = new Supplier($data);

        $this->suppliers->save($supplier);

        return $this->success($supplier, __('body.supplier_created'));
    }

    public function update(Supplier $supplier, array $data): array
    {
        $supplier->fill($data);

        $this->suppliers->save($supplier);

        return $this->success($supplier, __('body.supplier_updated'));
    }


    public function updateStatus(Supplier $supplier, SupplierStatus $status): array
    {
        $supplier->status = $status;

        $this->suppliers->save($supplier);

        return $this->success($supplier, __('body.supplier_status_updated'));
    }

    public function delete(Supplier $supplier): array
    {
        $supplier->delete();

        return $this->success(null, __('body.supplier_deleted'));
    }
}
