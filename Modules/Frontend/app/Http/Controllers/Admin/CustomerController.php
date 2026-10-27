<?php

namespace Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Frontend\Enums\CustomerStatus;
use Modules\Frontend\Models\Customer;

/**
 * Customers who signed up on this business's website (scoped by
 * BelongsToBusiness, so {customer} never resolves to another business's).
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('frontend::admin.customers.index', [
            'customers' => Customer::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CustomerStatus::class)],
        ]);

        $customer->status = CustomerStatus::from($data['status']);
        $customer->save();

        return back()->with('success', __('body.customer_status_updated'));
    }
}
