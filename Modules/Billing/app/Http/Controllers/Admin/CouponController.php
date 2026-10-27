<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Billing\Http\Requests\SaveCouponRequest;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Package;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('billing::admin.coupons.index', [
            'coupons' => Coupon::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('billing::admin.coupons.form', [
            'coupon' => new Coupon(['type' => 'percent', 'duration' => 'once', 'is_active' => true]),
            'packages' => Package::query()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function store(SaveCouponRequest $request): RedirectResponse
    {
        Coupon::query()->create($request->couponData());

        return redirect()->route('superadmin.billing.coupons.index')->with('success', __('billing::billing.coupon_saved'));
    }

    public function edit(Coupon $coupon): View
    {
        return view('billing::admin.coupons.form', [
            'coupon' => $coupon,
            'packages' => Package::query()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function update(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->couponData());

        return redirect()->route('superadmin.billing.coupons.index')->with('success', __('billing::billing.coupon_saved'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('superadmin.billing.coupons.index')->with('success', __('billing::billing.coupon_deleted'));
    }
}
