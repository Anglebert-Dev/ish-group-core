<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\FeeRateChange;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BusinessProfiles;
use Modules\InjongePay\Services\Fees;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $businesses = Business::query()
            ->whereKeyNot((int) System::getProperty('primary_business_id'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhereIn('id', BusinessProfile::query()->withoutGlobalScope('business')->where('tin', 'like', "%{$search}%")->select('business_id'))))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $ids = $businesses->pluck('id');

        return view('injongepay::platform.businesses.index', [
            'businesses' => $businesses,
            'profiles' => BusinessProfile::query()->withoutGlobalScope('business')->whereIn('business_id', $ids)->get()->keyBy('business_id'),
            'wallets' => Wallet::query()->withoutGlobalScopes()->whereIn('business_id', $ids)->whereNull('deleted_at')
                ->selectRaw('business_id, COUNT(*) AS count, SUM(pending_balance + available_balance) AS balance')
                ->groupBy('business_id')->get()->keyBy('business_id'),
            'search' => $search,
        ]);
    }

    public function edit(Business $business, Fees $fees): View
    {
        return view('injongepay::platform.businesses.edit', [
            'business' => $business,
            'profile' => BusinessProfile::for($business),
            'platformRate' => $fees->rateFor(0),
            'staff' => User::query()->where('business_id', (int) System::getProperty('primary_business_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'feeHistory' => FeeRateChange::query()->where('business_id', $business->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Business $business, BusinessProfiles $profiles): RedirectResponse
    {
        $current = BusinessProfile::for($business);

        $data = $request->validate([
            'short_name' => ['nullable', 'string', 'max:100'],
            'tin' => ['required', 'digits:9', Rule::unique('ijp_business_profiles', 'tin')->where('country', $request->input('country', 'RW'))->ignore($current->id)],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'regex:/^\+250\d{9}$/'],
            'contact_email' => ['required', 'email', 'max:255'],
            'fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'support_staff_id' => ['nullable', Rule::exists('users', 'id')->where('business_id', (int) System::getProperty('primary_business_id'))],
        ], [
            'contact_phone.regex' => __('injongepay::injongepay.err_phone_format'),
        ]);

        $profiles->save($business, $data);

        return redirect()->route('injongepay.platform.businesses.edit', $business)->with('success', __('injongepay::injongepay.profile_saved'));
    }
}
