<?php

namespace Modules\Billing\Services;

use App\Models\Business\Business;
use App\Services\BaseService;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Package;

class CouponService extends BaseService
{
    /**
     * @return array{status: bool, data: ?Coupon, message: string}
     */
    public function validate(?string $code, Business $business, Package $package): array
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return $this->success(null);
        }

        $coupon = Coupon::query()->where('code', $code)->first();

        $error = match (true) {
            ! $coupon || ! $coupon->is_active => __('billing::billing.coupon_invalid'),
            $coupon->expires_at && $coupon->expires_at->isPast() => __('billing::billing.coupon_expired'),
            $coupon->max_redemptions !== null && $coupon->redemptions_count >= $coupon->max_redemptions => __('billing::billing.coupon_used_up'),
            ! $coupon->appliesTo($package) => __('billing::billing.coupon_not_for_package'),
            CouponRedemption::query()->where('coupon_id', $coupon->id)->where('business_id', $business->id)->exists() => __('billing::billing.coupon_already_used'),
            default => null,
        };

        return $error ? $this->failure($error) : $this->success($coupon);
    }

    public function redeem(Coupon $coupon, Business $business, ?int $subscriptionId): void
    {
        CouponRedemption::query()->firstOrCreate(
            ['coupon_id' => $coupon->id, 'business_id' => $business->id],
            ['subscription_id' => $subscriptionId],
        );
        $coupon->increment('redemptions_count');
    }
}
