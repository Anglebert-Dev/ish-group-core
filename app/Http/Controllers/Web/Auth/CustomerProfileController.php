<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateCustomerPasswordRequest;
use App\Http\Requests\Auth\UpdateCustomerProfileRequest;
use App\Services\Auth\CustomerAuthService;
use App\Services\Customer\CustomerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerProfileController extends Controller
{
    public function __construct(
        protected CustomerAuthService $auth,
        protected CustomerPortalService $portal,
    ) {
    }

    public function dashboard(): View
    {
        $customer = Auth::guard('customer')->user();

        return view('public.account.dashboard', array_merge(
            ['customer' => $customer],
            $this->portal->snapshot($customer),
        ));
    }

    public function show(): View
    {
        return view('public.account.profile', [
            'customer' => Auth::guard('customer')->user(),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $result = $this->auth->updateProfile(Auth::guard('customer')->user(), $request->validated());

        return back()->with('success', $result['message']);
    }

    public function updatePassword(UpdateCustomerPasswordRequest $request): RedirectResponse
    {
        $result = $this->auth->updatePassword(Auth::guard('customer')->user(), $request->validated());

        if (! $result['status']) {
            return back()->withErrors(['current_password' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
