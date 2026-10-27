<?php

namespace App\Services\CorporateCustomer;

use App\Enums\CorporateCustomerStatus;
use App\Libraries\ListExport;
use App\Models\CorporateCustomer\CorporateContact;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use App\Repositories\CorporateCustomer\CorporateCustomerRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CorporateCustomerService extends BaseService
{
    public function __construct(protected CorporateCustomerRepository $corporateCustomers)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->corporateCustomers->query()->orderBy('company_name')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->corporateCustomers->query()
            ->orderBy('company_name')
            ->get()
            ->map(fn (CorporateCustomer $corporate) => [
                $corporate->company_name,
                $corporate->email ?? '—',
                $corporate->phone ?? '—',
                ucfirst($corporate->status->value),
            ])
            ->all();

        return ListExport::make(__('body.corporate_customers'), 'corporate-customers', [
            __('body.company_name'),
            __('body.email_address'),
            __('body.phone'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $customer = new CorporateCustomer($data);

        $this->corporateCustomers->save($customer);

        $this->syncContacts($customer, $data['contacts'] ?? []);

        return $this->success($customer, __('body.corporate_customer_created'));
    }

    public function createFromRegistration(Customer $customer, array $data): CorporateCustomer
    {
        $corporate = new CorporateCustomer([
            'company_name' => $data['company_name'],
            'tin' => $data['tin'] ?? null,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ]);
        $corporate->status = CorporateCustomerStatus::Active;

        $this->corporateCustomers->save($corporate);

        CorporateContact::create([
            'corporate_customer_id' => $corporate->id,
            'customer_id' => $customer->id,
            'type' => 'primary',
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ]);

        return $corporate;
    }

    public function update(CorporateCustomer $customer, array $data): array
    {
        $customer->fill($data);

        $this->corporateCustomers->save($customer);

        $this->syncContacts($customer, $data['contacts'] ?? []);

        return $this->success($customer, __('body.corporate_customer_updated'));
    }


    public function updateStatus(CorporateCustomer $customer, CorporateCustomerStatus $status): array
    {
        $customer->status = $status;

        $this->corporateCustomers->save($customer);

        return $this->success($customer, __('body.corporate_customer_status_updated'));
    }

    public function delete(CorporateCustomer $customer): array
    {
        $customer->delete();

        return $this->success(null, __('body.corporate_customer_deleted'));
    }

    protected function syncContacts(CorporateCustomer $customer, array $contacts): void
    {
        foreach (['primary', 'booking', 'finance'] as $type) {
            $contactData = $contacts[$type] ?? null;

            if (empty($contactData['name'])) {
                $customer->contacts()->where('type', $type)->delete();

                continue;
            }

            CorporateContact::updateOrCreate(
                ['corporate_customer_id' => $customer->id, 'type' => $type],
                [
                    'name' => $contactData['name'],
                    'email' => $contactData['email'] ?? null,
                    'phone' => $contactData['phone'] ?? null,
                ]
            );
        }
    }
}
