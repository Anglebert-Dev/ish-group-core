<?php

namespace App\Services\Customer;

use App\Enums\CustomerStatus;
use App\Libraries\ListExport;
use App\Models\Customer;
use App\Repositories\Customer\CustomerRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CustomerService extends BaseService
{
    public function __construct(protected CustomerRepository $customers)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->customers->query()->orderBy('name')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->customers->query()
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                $customer->name,
                $customer->email,
                $customer->phone ?? '—',
                $customer->national_id ?? '—',
                $customer->driver_license_number ?? '—',
                ucfirst($customer->status->value),
            ])
            ->all();

        return ListExport::make(__('body.customers'), 'customers', [
            __('body.full_name'),
            __('body.email_address'),
            __('body.phone'),
            __('body.national_id'),
            __('body.driver_license_id'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $customer = new Customer([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'address' => $data['address'] ?? null,
            'driver_license_number' => $data['driver_license_number'] ?? null,
            'password' => null,
        ]);
        $customer->status = CustomerStatus::Active;

        $this->customers->save($customer);

        return $this->success($customer, __('body.customer_created'));
    }

    /**
     * Create a portal login password for a customer that does not have one yet.
     *
     * @return array{status: bool, message: string, data?: array{customer: Customer, password: string}}
     */

    public function updateStatus(Customer $customer, CustomerStatus $status): array
    {
        $customer->status = $status;

        $this->customers->save($customer);

        return $this->success($customer, __('body.customer_status_updated'));
    }

    public function generatePortalAccount(Customer $customer): array
    {
        if ($customer->hasPortalPassword()) {
            return $this->failure(__('body.customer_portal_already_exists'));
        }

        return $this->issuePortalPassword($customer, __('body.customer_portal_generated'));
    }

    /**
     * Reset an existing portal password and return the new plain-text value once.
     *
     * @return array{status: bool, message: string, data?: array{customer: Customer, password: string}}
     */
    public function resetPortalPassword(Customer $customer): array
    {
        if (! $customer->hasPortalPassword()) {
            return $this->failure(__('body.customer_portal_missing'));
        }

        return $this->issuePortalPassword($customer, __('body.customer_portal_password_reset'));
    }

    /**
     * @return array{status: bool, message: string, data: array{customer: Customer, password: string}}
     */
    protected function issuePortalPassword(Customer $customer, string $message): array
    {
        $plainPassword = Str::password(16);

        $customer->forceFill([
            'password' => $plainPassword,
            'remember_token' => Str::random(60),
        ]);
        $this->customers->save($customer);

        return $this->success([
            'customer' => $customer->fresh(),
            'password' => $plainPassword,
        ], $message);
    }
}
