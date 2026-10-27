<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Libraries\ListExportLibrary;
use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Customer\UpdateCustomerStatusRequest;
use App\Enums\CustomerStatus;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customers)
    {
    }

    public function index(): View
    {
        return view('app.customers.index', [
            'customers' => $this->customers->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->customers->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->customers->exportList());
    }

    public function create(): View
    {
        return view('app.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $result = $this->customers->create($request->validated());

        return redirect()->route('customers.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Customer $customer): View
    {
        return view('app.customers.show', [
            'customer' => $customer,
        ]);
    }


    public function updateStatus(UpdateCustomerStatusRequest $request, Customer $customer): RedirectResponse
    {
        $status = CustomerStatus::from($request->validated('status'));
        $result = $this->customers->updateStatus($customer, $status);

        return redirect()
            ->route('customers.show', $customer)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function generatePortalAccount(Customer $customer): RedirectResponse
    {
        $result = $this->customers->generatePortalAccount($customer);

        if (! $result['status']) {
            return back()->with('error', $result['message']);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', $result['message'])
            ->with('portal_credentials', $this->credentialsPayload($result['data']['customer'], $result['data']['password']));
    }

    public function resetPortalPassword(Customer $customer): RedirectResponse
    {
        $result = $this->customers->resetPortalPassword($customer);

        if (! $result['status']) {
            return back()->with('error', $result['message']);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', $result['message'])
            ->with('portal_credentials', $this->credentialsPayload($result['data']['customer'], $result['data']['password']));
    }

    /**
     * @return array{email: string, phone: ?string, password: string}
     */
    protected function credentialsPayload(Customer $customer, string $password): array
    {
        return [
            'email' => $customer->email,
            'phone' => $customer->phone,
            'password' => $password,
        ];
    }
}
