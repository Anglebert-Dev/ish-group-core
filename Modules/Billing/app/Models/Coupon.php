<?php

namespace Modules\Billing\Models;

use App\Models\BaseModel;
use Modules\Billing\Enums\CouponDuration;
use Modules\Billing\Enums\CouponType;
use Modules\Billing\Support\Money;

class Coupon extends BaseModel
{
    protected $table = 'billing_coupons';

    protected $fillable = [
        'code', 'description', 'type', 'value', 'duration', 'duration_periods',
        'max_redemptions', 'redemptions_count', 'expires_at', 'package_ids', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'duration' => CouponDuration::class,
            'duration_periods' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'expires_at' => 'datetime',
            'package_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function name(): string
    {
        return $this->code;
    }

    public function appliesTo(Package $package): bool
    {
        return empty($this->package_ids) || in_array($package->id, array_map('intval', $this->package_ids), true);
    }

    /**
     * Discount in cents on an amount in cents (never more than the amount).
     */
    public function discountCents(int $amountCents): int
    {
        $discount = $this->type === CouponType::Percent
            ? (int) round($amountCents * min(100, (float) $this->value) / 100)
            : Money::toCents($this->value);

        return max(0, min($amountCents, $discount));
    }

    public function summary(): string
    {
        $value = $this->type === CouponType::Percent
            ? rtrim(rtrim((string) $this->value, '0'), '.').'%'
            : Money::format($this->value);

        return match ($this->duration) {
            CouponDuration::Once => __('billing::billing.coupon_summary_once', ['value' => $value]),
            CouponDuration::Repeating => __('billing::billing.coupon_summary_repeating', ['value' => $value, 'n' => $this->duration_periods]),
            CouponDuration::Forever => __('billing::billing.coupon_summary_forever', ['value' => $value]),
        };
    }
}
