<?php

namespace App\Http\Controllers\Web\Customer;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SaveCustomerRequest;
use App\Libraries\ListExportLibrary;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    private const FILTERS = ['q', 'status', 'type'];

    public function __construct(protected CustomerService $customers)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTERS);

        return view('app.customers.index', [
            'customers' => $this->customers->list($filters),
            'filters' => $filters,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->customers->exportList($request->only(self::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->customers->exportList($request->only(self::FILTERS)));
    }

    public function create(): View
    {
        return view('app.customers.form', ['customer' => new Customer(['type' => 'company'])]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $result = $this->customers->create($request->validated());

        return redirect()->route('customers.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Customer $customer): View
    {
        return view('app.customers.show', [
            'customer' => $customer,
            'links' => $this->customers->links($customer),
            'portalAvailable' => $this->customers->portalAvailable(),
            'portalUrl' => $this->customers->portalUrl($customer),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('app.customers.form', ['customer' => $customer]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $result = $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', $result['message']);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(CustomerStatus::class)]]);

        $result = $this->customers->setStatus($customer, CustomerStatus::from($data['status']));

        return back()->with('success', $result['message']);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $result = $this->customers->delete($customer);

        return $result['status']
            ? redirect()->route('customers.index')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    public function invite(Customer $customer): RedirectResponse
    {
        $result = $this->customers->invite($customer);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function revokePortal(Customer $customer): RedirectResponse
    {
        $result = $this->customers->revokePortal($customer);

        return back()->with('success', $result['message']);
    }
}
