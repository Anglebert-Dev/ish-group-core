<?php

namespace App\Services\Customer;

use App\Enums\ActivityLogAction;
use App\Enums\CustomerStatus;
use App\Libraries\ListExport;
use App\Models\Customer\Customer;
use App\Notifications\BrandedNotification;
use App\Repositories\Customer\CustomerRepository;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

/**
 * Customers of the signed-in business (scoped by BelongsToBusiness).
 */
class CustomerService extends BaseService
{
    public function __construct(
        protected CustomerRepository $customers,
        protected ModuleManager $modules,
    ) {
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->filtered($filters)->paginate(20)->withQueryString();
    }

    public function exportList(array $filters): ListExport
    {
        $rows = $this->filtered($filters)->limit(5000)->get()->map(fn (Customer $c) => [
            $c->displayName(),
            $c->type?->label() ?? '—',
            $c->contact_person ?: '—',
            $c->phone ?: '—',
            $c->email ?: '—',
            $c->tax_id ?: '—',
            $c->status->label(),
        ])->all();

        return ListExport::make(__('body.customers'), 'customers', [
            __('body.name'), __('body.type'), __('body.customer_contact_person'), __('body.phone'),
            __('body.email_address'), __('body.customer_tax_id'), __('body.status'),
        ], $rows);
    }

    public function filtered(array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return Customer::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")))
            ->orderBy('name');
    }

    public function create(array $data): array
    {
        $customer = new Customer($data);
        $customer->status = CustomerStatus::Active;
        $this->customers->save($customer);

        return $this->success($customer, __('body.customer_created'));
    }

    public function update(Customer $customer, array $data): array
    {
        $customer->fill($data);
        $this->customers->save($customer);

        return $this->success($customer, __('body.customer_updated'));
    }

    public function setStatus(Customer $customer, CustomerStatus $status): array
    {
        $from = $customer->status;
        $customer->status = $status;
        $this->customers->save($customer);

        audit(ActivityLogAction::Updated, __('body.audit_log_customer_status', [
            'name' => $customer->displayName(), 'from' => $from?->label(), 'to' => $status->label(),
        ]), $customer);

        return $this->success($customer, __('body.customer_status_updated'));
    }

    /**
     * Linked records reported by modules (orders, invoices, …):
     * [module => count]. A customer with links cannot be deleted.
     *
     * @return array<string, int>
     */
    public function links(Customer $customer): array
    {
        return collect($this->modules->hook('customer_links', $customer))
            ->map(fn ($count) => is_array($count) ? array_sum($count) : (int) $count)
            ->filter(fn (int $count) => $count > 0)
            ->all();
    }

    public function delete(Customer $customer): array
    {
        if ($this->links($customer) !== []) {
            return $this->failure(__('body.customer_has_links'));
        }

        $customer->delete();

        return $this->success(null, __('body.customer_deleted'));
    }

    /**
     * Website login (needs the module that provides it, e.g. Frontend).
     */
    public function portalAvailable(): bool
    {
        return config('auth.passwords.customers') !== null && array_filter($this->modules->cachedHook('customer_portal_url')) !== [];
    }

    public function portalUrl(Customer $customer): ?string
    {
        foreach ($this->modules->hook('customer_portal_url', $customer) as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Email the customer a link to set their website password.
     */
    public function invite(Customer $customer): array
    {
        if (! $this->portalAvailable() || blank($customer->email)) {
            return $this->failure(__('body.customer_invite_needs_email'));
        }

        if ($customer->hasPortalPassword()) {
            Password::broker('customers')->sendResetLink([
                'email' => $customer->email,
                'business_id' => $customer->business_id,
            ]);
        } else {
            $this->sendInvitation($customer);
        }

        audit(ActivityLogAction::Sent, __('body.audit_log_customer_invited', ['name' => $customer->displayName()]), $customer);

        return $this->success($customer, __('body.customer_invited', ['email' => $customer->email]));
    }

    private function sendInvitation(Customer $customer): void
    {
        $business = $customer->business;
        $token = Password::broker('customer_invites')->createToken($customer);
        $url = $customer->passwordResetUrl($token).'&welcome=1';

        $customer->notify(new BrandedNotification(
            $business,
            __('body.email_invite_subject', ['business' => $business?->name]),
            [__('body.email_invite_line', ['name' => $customer->name, 'business' => $business?->name])],
            __('body.email_invite_action'),
            $url,
            [__('body.email_invite_expiry'), __('body.email_invite_login', ['url' => $this->portalUrl($customer)])],
        ));
    }

    public function revokePortal(Customer $customer): array
    {
        $customer->forceFill(['password' => null, 'remember_token' => null])->save();

        audit(ActivityLogAction::Updated, __('body.audit_log_customer_portal_revoked', ['name' => $customer->displayName()]), $customer);

        return $this->success($customer, __('body.customer_portal_revoked'));
    }
}
