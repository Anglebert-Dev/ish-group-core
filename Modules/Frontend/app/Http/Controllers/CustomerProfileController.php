<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Frontend\Http\Requests\UpdateCustomerPasswordRequest;
use Modules\Frontend\Http\Requests\UpdateCustomerProfileRequest;
use Modules\Frontend\Services\CustomerAuthService;

class CustomerProfileController extends Controller
{
    public function __construct(protected CustomerAuthService $auth)
    {
    }

    public function dashboard(): View
    {
        return view('frontend::account.dashboard', [
            'customer' => Auth::guard('customer')->user(),
        ]);
    }

    public function show(): View
    {
        return view('frontend::account.profile', [
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
