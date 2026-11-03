<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Support\Rate;

/** The business's own details. TIN, fee and limits stay with Inhills (they go to GTBank and set the rules). */
class ProfileController extends Controller
{
    public const EDITABLE = ['short_name', 'city', 'address', 'contact_person', 'contact_phone', 'contact_email'];

    public function edit(Request $request): View
    {
        return view('injongepay::business.profile', [
            'profile' => BusinessProfile::for($request->user('staff')->business),
            'fee' => Rate::display((string) app(\Modules\InjongePay\Services\Fees::class)->rateFor((int) $request->user('staff')->business_id)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'short_name' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'regex:/^\+250\d{9}$/'],
            'contact_email' => ['required', 'email', 'max:255'],
        ], ['contact_phone.regex' => __('injongepay::injongepay.err_phone_format')]);

        $profile = BusinessProfile::for($request->user('staff')->business);
        $profile->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
        $profile->code ??= 'INJP'.now()->format('Ymd').str_pad((string) \Modules\InjongePay\Support\Counter::next('business_code'), 4, '0', STR_PAD_LEFT);
        $profile->save();

        return back()->with('success', __('injongepay::injongepay.profile_saved'));
    }
}
