<?php

namespace Modules\Billing\Enums;

enum CouponDuration: string
{
    case Once = 'once';            // first invoice only
    case Repeating = 'repeating';  // first N invoices
    case Forever = 'forever';

    public function label(): string
    {
        return __('billing::billing.coupon_duration_'.$this->value);
    }
}
