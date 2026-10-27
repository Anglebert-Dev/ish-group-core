<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    protected $table = 'billing_coupon_redemptions';

    protected $fillable = ['coupon_id', 'business_id', 'subscription_id'];
}
